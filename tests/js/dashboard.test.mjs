import assert from 'node:assert/strict';
import { after, before, describe, it } from 'node:test';
import { startBrowser, unavailable } from './browser.mjs';

const skip = unavailable() ?? false;
const CONTENT = 'https://example.com/team-poster';

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

    const drawn = () => tab.evaluate(`Array.from(document.querySelectorAll('[data-qr-content]'), (el) => ({ content: el.dataset.qrContent, svg: el.querySelector('svg') !== null }))`);

    it('draws every code in the table, with or without a Design', async () => {
        await tab.open(`${browser.origin}/admin/qr-codes`);
        await tab.until(async () => (await drawn()).length === 2 && (await drawn()).every((code) => code.svg), 'both codes to be drawn');

        assert.deepEqual((await drawn()).map((code) => code.content).sort(), ['https://example.com/plain', CONTENT]);
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
});
