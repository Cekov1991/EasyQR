import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));

/**
 * Drives the locally installed Chrome over the DevTools protocol with Node's
 * built-in WebSocket, against the real Laravel app served by PHP's own server on
 * a throwaway SQLite database. No dependency is added; when Chrome or PHP is not
 * found the caller skips with the message `unavailable()` returns.
 */

const CHROME_CANDIDATES = [
    process.env.CHROME_PATH,
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
];

export function chromePath() {
    return CHROME_CANDIDATES.find((path) => path && existsSync(path)) ?? null;
}

/** Why the browser tests cannot run here, or null when they can. */
export function unavailable() {
    if (typeof WebSocket !== 'function') {
        return 'Node has no built-in WebSocket (needs Node 22+)';
    }

    if (!chromePath()) {
        return 'Chrome was not found (set CHROME_PATH to run the browser tests)';
    }

    if (spawnSync('php', ['-v']).status !== 0) {
        return 'PHP was not found, so the app cannot be served';
    }

    return null;
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export { wait };

function freePort() {
    return new Promise((resolve, reject) => {
        const server = createServer();

        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const { port } = server.address();

            server.close(() => resolve(port));
        });
    });
}

async function until(check, what, timeout = 15000) {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        const value = await check();

        if (value) {
            return value;
        }

        await wait(100);
    }

    throw new Error(`Timed out waiting for ${what}`);
}

/** Serves the app on a fresh SQLite database; resolves to its origin and a stop function. */
async function serveApp(directory) {
    const database = join(directory, 'app.sqlite');
    const port = await freePort();
    const env = {
        ...process.env,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
        QUEUE_CONNECTION: 'sync',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'critical',
        PULSE_ENABLED: 'false',
    };

    writeFileSync(database, '');

    const migrated = spawnSync('php', ['artisan', 'migrate', '--force', '--no-interaction'], { cwd: ROOT, env });

    if (migrated.status !== 0) {
        throw new Error(`Could not prepare the test database: ${migrated.stderr || migrated.stdout}`);
    }

    const router = join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', '.', router], {
        cwd: join(ROOT, 'public'),
        env,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    const origin = `http://127.0.0.1:${port}`;

    server.stdout.resume();
    server.stderr.resume();

    await until(async () => {
        try {
            return (await fetch(`${origin}/`)).status === 200;
        } catch {
            return false;
        }
    }, 'the app to answer');

    return {
        origin,
        stop() {
            server.kill();
            server.stdout.destroy();
            server.stderr.destroy();
            server.unref();
        },
    };
}

/** Starts Chrome headless and resolves to its DevTools WebSocket URL. */
function startChrome(directory) {
    return new Promise((resolve, reject) => {
        const chrome = spawn(chromePath(), [
            '--headless=new',
            '--remote-debugging-port=0',
            `--user-data-dir=${join(directory, 'chrome')}`,
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-gpu',
            '--disable-extensions',
            '--hide-scrollbars',
            'about:blank',
        ], { stdio: ['ignore', 'ignore', 'pipe'] });
        let log = '';

        chrome.stderr.on('data', (chunk) => {
            log += chunk;
            const found = /DevTools listening on (ws:\/\/\S+)/.exec(log);

            if (found) {
                resolve({
                    endpoint: found[1],
                    stop() {
                        chrome.kill();
                        chrome.stderr.destroy();
                        chrome.unref();
                    },
                });
            }
        });
        chrome.once('exit', (code) => reject(new Error(`Chrome exited (${code}) before it was ready:\n${log}`)));
    });
}

class Connection {
    constructor(socket) {
        this.socket = socket;
        this.nextId = 1;
        this.pending = new Map();
        this.listeners = [];
        socket.addEventListener('message', (message) => {
            const data = JSON.parse(message.data);

            if (data.id && this.pending.has(data.id)) {
                const { resolve, reject } = this.pending.get(data.id);

                this.pending.delete(data.id);
                data.error ? reject(new Error(`${data.error.message} ${data.error.data ?? ''}`)) : resolve(data.result);
            } else if (data.method) {
                this.listeners.forEach((listener) => listener(data));
            }
        });
    }

    send(method, params = {}, sessionId) {
        const id = this.nextId++;

        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
        });
    }

    on(listener) {
        this.listeners.push(listener);
    }
}

/** One tab. Collects the requests it makes and the exceptions it throws. */
export class Tab {
    constructor(connection, sessionId, targetId) {
        this.connection = connection;
        this.sessionId = sessionId;
        this.targetId = targetId;
        this.requests = [];
        this.exceptions = [];
        connection.on((event) => {
            if (event.sessionId !== sessionId) {
                return;
            }

            if (event.method === 'Network.requestWillBeSent') {
                const { request } = event.params;

                this.requests.push({ id: event.params.requestId, url: request.url, method: request.method, body: request.postData ?? '', status: null });
            } else if (event.method === 'Network.responseReceived') {
                const known = this.requests.find((request) => request.id === event.params.requestId);

                if (known) {
                    known.status = event.params.response.status;
                }
            } else if (event.method === 'Runtime.exceptionThrown') {
                this.exceptions.push(event.params.exceptionDetails.exception?.description ?? event.params.exceptionDetails.text);
            }
        });
    }

    send(method, params) {
        return this.connection.send(method, params, this.sessionId);
    }

    async evaluate(expression) {
        const { result, exceptionDetails } = await this.send('Runtime.evaluate', {
            expression,
            awaitPromise: true,
            returnByValue: true,
        });

        if (exceptionDetails) {
            throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text);
        }

        return result.value;
    }

    /** Runs a script before any of the page's own, on every load of this tab. */
    beforeLoad(source) {
        return this.send('Page.addScriptToEvaluateOnNewDocument', { source });
    }

    async open(url) {
        await this.send('Page.navigate', { url });
        await until(() => this.evaluate('document.readyState === "complete"'), `${url} to load`);
    }

    async setWidth(width, height = 900) {
        await this.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    }

    async type(selector, text) {
        await this.evaluate(`document.querySelector(${JSON.stringify(selector)}).focus()`);

        for (const character of text) {
            await this.send('Input.insertText', { text: character });
        }
    }

    async setFiles(selector, files) {
        const { root } = await this.send('DOM.getDocument');
        const { nodeId } = await this.send('DOM.querySelector', { nodeId: root.nodeId, selector });

        await this.send('DOM.setFileInputFiles', { nodeId, files });
    }

    key(key) {
        const event = { key, code: key, windowsVirtualKeyCode: key === 'Escape' ? 27 : 0 };

        return this.send('Input.dispatchKeyEvent', { type: 'keyDown', ...event })
            .then(() => this.send('Input.dispatchKeyEvent', { type: 'keyUp', ...event }));
    }

    click(selector) {
        return this.evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    }

    until(check, what, timeout) {
        return until(check, what, timeout);
    }

    /** Requests this tab has made to the app's /events endpoint, as parsed bodies. */
    events() {
        return this.requests
            .filter((request) => request.method === 'POST' && new URL(request.url).pathname === '/events')
            .map((request) => JSON.parse(request.body));
    }

    eventNames() {
        return this.events().map((event) => event.event);
    }
}

/** Starts the app and Chrome; `tab()` opens a clean tab, `stop()` tears both down. */
export async function startBrowser() {
    const directory = mkdtempSync(join(tmpdir(), 'eq-browser-'));
    const app = await serveApp(directory);
    const chrome = await startChrome(directory);
    const socket = new WebSocket(chrome.endpoint);

    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });

    const connection = new Connection(socket);
    const tabs = [];

    return {
        origin: app.origin,

        /** A tab in a browser context of its own, so no storage is shared with any other tab. */
        async tab() {
            const { browserContextId } = await connection.send('Target.createBrowserContext');
            const { targetId } = await connection.send('Target.createTarget', { url: 'about:blank', browserContextId });
            const { sessionId } = await connection.send('Target.attachToTarget', { targetId, flatten: true });
            const tab = new Tab(connection, sessionId, targetId);

            await tab.send('Page.enable');
            await tab.send('Network.enable');
            await tab.send('Runtime.enable');
            await connection.send('Browser.setDownloadBehavior', { behavior: 'deny', browserContextId });
            tabs.push(tab);

            return tab;
        },

        async stop() {
            try {
                socket.close();
            } catch {
                // Already closed.
            }

            chrome.stop();
            app.stop();
            await wait(200);
            rmSync(directory, { recursive: true, force: true });
        },
    };
}
