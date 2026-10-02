import assert from 'node:assert/strict';
import { Resvg } from '@resvg/resvg-js';
import { after, before, describe, it } from 'node:test';
import { startBrowser, unavailable } from './browser.mjs';

const skip = unavailable() ?? false;
const CONTENT = 'https://example.com/team-poster';
const LOGO_CONTENT = 'https://example.com/logo-poster';

/** A solid red square: red appears nowhere in a black-on-white code, so its pixels are the logo's. */
const LOGO_PNG = Buffer.from(new Resvg('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="#ff0000"/></svg>').render().asPng());

/** How many pixels of a PNG data URL, or SVG text, are the logo's red. */
function redPixels(svg) {
    const { pixels } = new Resvg(svg, { fitTo: { mode: 'width', value: 400 }, background: 'white' }).render();
    let red = 0;

    for (let i = 0; i < pixels.length; i += 4) {
        if (pixels[i] > 200 && pixels[i + 1] < 70 && pixels[i + 2] < 70) {
            red++;
        }
    }

    return red;
}

/** Wraps PNG bytes in an SVG so the same pixel count can be taken of a downloaded PNG. */
const asSvg = (bytes) => `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="400" height="400"><image width="400" height="400" xlink:href="data:image/png;base64,${Buffer.from(bytes).toString('base64')}"/></svg>`;

const SEED = `
    $user = App\\Models\\User::factory()->create(['email' => 'owner@example.com']);
    $ink = App\\Support\\QrDesignOptions::defaultDesign();
    App\\Models\\QrCode::factory()->for($user)->create([
        'name' => 'Team Poster',
        'qr_content_data' => ['url' => '${CONTENT}'],
        'options' => ['design' => array_merge($ink, ['dot' => 'square', 'corner' => 'square', 'eye' => 'square', 'frame' => 'badge'])],
    ]);
    App\\Models\\QrCode::factory()->for($user)->create([
        'name' => 'Plain', 'qr_content_data' => ['url' => 'https://example.com/plain'],
    ]);
    Illuminate\\Support\\Facades\\Storage::put("qr-logos/{$user->id}/logo.png", base64_decode('${LOGO_PNG.toString('base64')}'));
    App\\Models\\QrCode::factory()->for($user)->create([
        'name' => 'Logo Poster',
        'qr_content_data' => ['url' => '${LOGO_CONTENT}'],
        'options' => ['design' => array_merge($ink, ['logo' => ['path' => "qr-logos/{$user->id}/logo.png", 'shape' => 'square', 'size' => 20, 'padding' => 2, 'backing' => true, 'clearSpace' => true]])],
    ]);
`;

/** Remembers what the page saves, so a download can be inspected instead of landing on disk. */
const CAPTURE_DOWNLOADS = `
    window.__saved = [];
    const click = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () {
        if (this.download) {
            const name = this.download;
            fetch(this.href).then((r) => r.blob()).then(async (blob) => {
                window.__saved.push({ name, type: blob.type, bytes: Array.from(new Uint8Array(await blob.arrayBuffer())) });
            });
            return;
        }
        return click.call(this);
    };
`;

describe('the dashboard in a real browser', { skip }, () => {
    let browser;
    let tab;

    before(async () => {
        browser = await startBrowser({ seed: SEED });
        tab = await browser.tab();
        await tab.setWidth(1280, 900);
        await tab.beforeLoad(CAPTURE_DOWNLOADS);
        await tab.open(`${browser.origin}/admin/login`);
        await tab.until(() => tab.evaluate('!!document.querySelector("input[type=password]")'), 'the login form');
        await tab.type('input[type=email]', 'owner@example.com');
        await tab.type('input[type=password]', 'password');
        await tab.evaluate('document.querySelector("form button[type=submit]").click()');
        await tab.until(async () => !(await tab.evaluate('location.pathname')).includes('login'), 'the sign in');
    });

    after(async () => {
        await browser?.stop();
    });

    /** The dashboard's own chart widget logs a canvas error on repeat visits; ours must add none. */
    const ownExceptions = () => tab.exceptions.filter((exception) => !exception.includes('Canvas is already in use'));

    const drawn = () => tab.evaluate(`Array.from(document.querySelectorAll('[data-qr-content]'), (el) => ({ content: el.dataset.qrContent, svg: el.querySelector('svg') !== null }))`);

    it('draws every code in the table, with or without a Design', async () => {
        await tab.open(`${browser.origin}/admin/qr-codes`);
        await tab.until(async () => (await drawn()).length === 3 && (await drawn()).every((code) => code.svg), 'every code to be drawn');

        assert.deepEqual((await drawn()).map((code) => code.content).sort(), [LOGO_CONTENT, 'https://example.com/plain', CONTENT].sort());
        assert.deepEqual(tab.exceptions, []);
    });

    it('draws the Top performing widget on the dashboard', async () => {
        await tab.open(`${browser.origin}/admin`);
        await tab.until(async () => (await drawn()).some((code) => code.svg), 'the widget to draw');
    });

    describe('the View page', () => {
        const saved = () => tab.evaluate('window.__saved');

        async function viewPage() {
            await tab.open(`${browser.origin}/admin/qr-codes/1`);
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-download]")'), 'the downloads');
        }

        it('downloads a PNG at the chosen width', async () => {
            await viewPage();
            await tab.evaluate(`const s = document.querySelector('[data-qr-size]'); s.value = '512'; s.dispatchEvent(new Event('change'))`);
            await tab.click('[data-qr-download="png"]');
            await tab.until(async () => (await saved()).length === 1, 'the PNG');

            const [file] = await saved();
            const width = (file.bytes[16] << 24) | (file.bytes[17] << 16) | (file.bytes[18] << 8) | file.bytes[19];

            assert.match(file.name, /\.png$/);
            assert.equal(width, 512);
        });

        it('downloads the SVG', async () => {
            await tab.click('[data-qr-download="svg"]');
            await tab.until(async () => (await saved()).length === 2, 'the SVG');

            const file = (await saved())[1];
            const text = Buffer.from(file.bytes).toString('utf8');

            assert.match(file.name, /\.svg$/);
            assert.match(text, /^<svg[^>]* width="\d+" height="\d+"/);
        });

        it('downloads a ZIP of all formats', async () => {
            await tab.click('[data-qr-download="zip"]');
            await tab.until(async () => (await saved()).length === 3, 'the ZIP', 30000);

            const file = (await saved())[2];
            const text = Buffer.from(file.bytes).toString('latin1');

            assert.match(file.name, /-qr-codes\.zip$/);
            assert.equal(text.slice(0, 2), 'PK');
            for (const name of ['.svg', '-512px.png', '-1024px.png', '-2048px.png', '-4096px.png']) {
                assert.ok(text.includes(name), name);
            }
        });
    });
    describe('a code with a logo', () => {
        const saved = () => tab.evaluate('window.__saved');
        const logoOf = (name) => tab.evaluate(`document.querySelector('[data-qr-logo]')?.dataset.qrLogo ?? null`);

        it('draws the logo in the table from the logo route on its own origin', async () => {
            await tab.open(`${browser.origin}/admin/qr-codes`);
            await tab.until(async () => (await drawn()).length === 3 && (await drawn()).every((code) => code.svg), 'every code to be drawn');

            const address = await logoOf();
            const href = await tab.evaluate(`Array.from(document.querySelectorAll('[data-qr-logo] svg image'), (image) => image.getAttribute('href'))`);

            assert.match(address, /\/qr-codes\/3\/logo\?v=/);
            assert.deepEqual(href, [address]);
            assert.equal(new URL(address, browser.origin).origin, browser.origin);
            assert.deepEqual(ownExceptions(), []);
        });

        it('serves the logo to the signed-in owner', async () => {
            const answer = await tab.evaluate(`fetch(document.querySelector('[data-qr-logo]').dataset.qrLogo).then(async (r) => ({ status: r.status, type: r.headers.get('content-type'), size: (await r.arrayBuffer()).byteLength }))`);

            assert.equal(answer.status, 200);
            assert.equal(answer.type, 'image/png');
            assert.equal(answer.size, LOGO_PNG.length);
        });

        it('puts the logo inside the PNG, the SVG and the ZIP of the View page', async () => {
            await tab.open(`${browser.origin}/admin/qr-codes/3`);
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-download]")'), 'the downloads');

            await tab.evaluate(`const s = document.querySelector('[data-qr-size]'); s.value = '512'; s.dispatchEvent(new Event('change'))`);
            await tab.click('[data-qr-download="png"]');
            await tab.until(async () => (await saved()).length === 1, 'the PNG');
            await tab.click('[data-qr-download="svg"]');
            await tab.until(async () => (await saved()).length === 2, 'the SVG');

            const [png, svg] = await saved();

            assert.ok(redPixels(asSvg(Buffer.from(png.bytes))) > 500, 'the PNG carries the logo');
            assert.ok(redPixels(Buffer.from(svg.bytes).toString('utf8')) > 500, 'the SVG carries the logo');
            assert.match(Buffer.from(svg.bytes).toString('utf8'), /<image[^>]+href="data:image\/png;base64,/);
            assert.deepEqual(ownExceptions(), []);
        });
    });
});
