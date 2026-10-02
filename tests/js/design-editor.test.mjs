import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Resvg } from '@resvg/resvg-js';
import { after, before, describe, it } from 'node:test';
import { startBrowser, unavailable } from './browser.mjs';
import { SAMPLE_PIXELS_PER_MODULE, decode } from './support.mjs';

const skip = unavailable() ?? false;
const URL_TO_ENCODE = 'https://example.com/spring-menu';

/** A wide picture on disk, so squaring it is part of what the upload does. */
const PICTURE = join(mkdtempSync(join(tmpdir(), 'eq-logo-')), 'logo.png');
writeFileSync(PICTURE, new Resvg('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><rect width="300" height="100" fill="#d92d20"/></svg>').render().asPng());

const SEED = `
    $user = App\\Models\\User::factory()->create(['email' => 'owner@example.com']);
    App\\Models\\QrCode::factory()->for($user)->dynamic()->create([
        'name' => 'Old poster', 'short_url' => 'aB3dE5gH',
        'options' => ['design' => array_merge(App\\Support\\QrDesignOptions::defaultDesign(), ['dot' => 'dots'])],
    ]);
`;

describe('the Design editor in a real browser', { skip }, () => {
    let browser;
    let tab;

    before(async () => {
        browser = await startBrowser({ seed: SEED });
        tab = await browser.tab();
        await tab.setWidth(1400, 1000);
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

    const submit = () => tab.evaluate('document.querySelector("main form button[type=submit]").click()');
    const previewSvg = () => tab.evaluate('document.querySelector("[data-studio-code] svg")?.outerHTML ?? null');
    /** What the preview says when scanned. jsQR is fussy about a single raster size, so a few are tried. */
    async function readPreview() {
        const svg = await previewSvg();

        const modules = svg ? Number(/viewBox="0 0 ([\d.]+)/.exec(svg)[1]) / 10 : 0;

        for (const pixelsPerModule of SAMPLE_PIXELS_PER_MODULE) {
            const text = svg ? decode(svg, Math.round(modules * pixelsPerModule)) : null;

            if (text) {
                return text;
            }
        }

        return null;
    }

    /** The browser tests run Chrome headless, where Filament's own file upload throws on load; ours must not. */
    const ownExceptions = () => tab.exceptions.filter((exception) => !exception.includes('file-upload.js'));

    const wireGet = (path) => tab.evaluate(`Livewire.all()[0].$wire.$get(${JSON.stringify(path)})`);
    const setField = (id, value) => tab.evaluate(`(() => {
        const field = document.getElementById(${JSON.stringify(id)});
        field.value = ${JSON.stringify(value)};
        field.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);

    async function openCreate() {
        await tab.open(`${browser.origin}/admin/qr-codes/create`);
        await tab.until(() => tab.evaluate('!!document.querySelector(".eq-studio [data-look]")'), 'the editor');
    }

    describe('creating a code', () => {
        before(openCreate);

        it('opens in the Rounded Look with nothing to draw yet', async () => {
            assert.equal(await tab.evaluate('document.querySelector("[data-studio-look-name]").textContent'), 'Rounded');
            assert.equal(await previewSvg(), null);
            assert.equal(await tab.evaluate('document.querySelector("[data-studio-empty]").hidden'), false);
            assert.deepEqual(ownExceptions(), []);
        });

        it('draws exactly the code that the fields encode, a moment after they change', async () => {
            await setField('data.qr_content_data.url', URL_TO_ENCODE);
            await tab.until(previewSvg, 'the preview to draw');

            assert.equal(await readPreview(), URL_TO_ENCODE);
        });

        it('redraws when the link changes', async () => {
            await setField('data.qr_content_data.url', `${URL_TO_ENCODE}-2`);
            await tab.until(async () => (await readPreview()) === `${URL_TO_ENCODE}-2`, 'the preview to follow');
        });

        it('encodes the reserved Short URL for a Dynamic code', async () => {
            const reserved = await wireGet('data.short_url');

            await tab.evaluate(`Livewire.all()[0].$wire.$set('data.type', 'dynamic', false)`);
            await tab.until(async () => (await readPreview()) === `${browser.origin}/q/${reserved}`, 'the Short URL in the preview');
        });

        it('shows the Look indicator as Custom once a setting differs, and a Look copies its settings', async () => {
            await tab.click('[data-setting="dot"][data-value="square"]');
            assert.equal(await tab.evaluate('document.querySelector("[data-studio-look-name]").textContent'), 'Custom');
            assert.equal(await wireGet('data.options.design.dot'), 'square');

            await tab.click('[data-look="bloom"]');
            assert.equal(await tab.evaluate('document.querySelector("[data-studio-look-name]").textContent'), 'Bloom');
            assert.equal(await wireGet('data.options.design.dot'), 'dots');
            assert.equal(await wireGet('data.options.design.codeColor'), '#2C7790');
        });

        it('says why a design that would not scan is blocked, and the server refuses to save it', async () => {
            await tab.evaluate(`(() => {
                for (const [id, value] of [['codeColor', '#888888'], ['bgColor', '#999999']]) {
                    const field = document.querySelector('[data-colour-hex="' + id + '"]');
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            })()`);
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-studio-notices] [data-tone=blocked]")'), 'the blocked notice');

            assert.match(await tab.evaluate('document.querySelector("[data-studio-notices] [data-tone=blocked]").textContent'), /too close in colour/);

            await setField('data.name', 'Spring menu');
            await submit();
            await tab.until(() => tab.evaluate('document.body.innerText.includes("too close in colour") && document.querySelectorAll("[data-validation-error], .fi-fo-field-wrp-error-message").length > 0'), 'the refusal');
            assert.match(await tab.evaluate('location.pathname'), /\/create$/);
        });

        it('saves the design it shows and then draws the code from it', async () => {
            await tab.click('[data-look="ink"]');
            await submit();
            await tab.until(async () => /\/admin\/qr-codes\/\d+$/.test(await tab.evaluate('location.pathname')), 'the saved code');
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-design]")'), 'the View page drawing');

            const saved = await tab.evaluate('JSON.parse(document.querySelector("[data-qr-design]").dataset.qrDesign)');

            assert.equal(saved.version, 1);
            assert.equal(saved.dot, 'square');
            assert.equal(saved.codeColor, '#000000');
            assert.match(await tab.evaluate('document.querySelector("[data-qr-content]").dataset.qrContent'), /\/q\/[2-9a-zA-Z]{8}$/);
        });
    });

    describe('editing a code', () => {
        before(async () => {
            await tab.open(`${browser.origin}/admin/qr-codes/1/edit`);
            await tab.until(() => tab.evaluate('!!document.querySelector(".eq-studio [data-look]")'), 'the editor');
        });

        it('opens on the design the code was saved with, drawing its stored Short URL', async () => {
            assert.equal(await tab.evaluate('document.querySelector("[data-studio-look-name]").textContent'), 'Custom');
            assert.equal(await tab.evaluate('document.querySelector("[data-setting=dot][data-value=dots]").getAttribute("aria-pressed")'), 'true');
            assert.equal(await readPreview(), `${browser.origin}/q/aB3dE5gH`);
        });

        it('restyles without changing the Short URL', async () => {
            await tab.click('[data-setting="frame"][data-value="badge"]');
            await submit();
            await tab.until(() => tab.evaluate('document.body.innerText.includes("Saved")'), 'the save to be confirmed');
            await tab.open(`${browser.origin}/admin/qr-codes/1`);
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-design]")'), 'the View page drawing');

            assert.equal(await tab.evaluate('JSON.parse(document.querySelector("[data-qr-design]").dataset.qrDesign).frame'), 'badge');
            assert.equal(await tab.evaluate('document.querySelector("[data-qr-content]").dataset.qrContent'), `${browser.origin}/q/aB3dE5gH`);
        });
    });

    describe('putting a logo on a code', () => {
        const logoOf = () => tab.evaluate(`JSON.parse(JSON.stringify(Livewire.all()[0].$wire.$get('data.options.design'))).logo ?? null`);
        const logoPath = async () => (await logoOf())?.path;
        const message = () => tab.evaluate('document.querySelector("[data-logo-message]").textContent');

        before(async () => {
            await openCreate();
            await setField('data.qr_content_data.url', URL_TO_ENCODE);
            await tab.until(previewSvg, 'the preview to draw');
        });

        it('uploads the picked picture, draws it in the preview and keeps the code scannable', async () => {
            await tab.setFiles('[data-logo-file]', [PICTURE]);
            await tab.until(logoPath, 'the upload to be stored');

            assert.match(await logoPath(), /^qr-logos\/\d+\/[A-Za-z0-9]+\.png$/);
            assert.equal(await message(), '');
            assert.match(await previewSvg(), /<image[^>]+href="data:image/);
            assert.equal(await readPreview(), URL_TO_ENCODE);
            assert.deepEqual(ownExceptions(), []);
        });

        it('keeps the logo settings in the Design the form saves', async () => {
            await tab.click('[data-setting="logoShape"][data-value="circle"]');

            assert.equal((await logoOf()).shape, 'circle');
            assert.equal((await logoOf()).size, 20);
        });

        it('saves the code, and every page then draws the logo from the logo route', async () => {
            await setField('data.name', 'Spring menu');
            await submit();
            await tab.until(async () => /\/admin\/qr-codes\/\d+$/.test(await tab.evaluate('location.pathname')), 'the saved code');
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-logo]")'), 'the View page drawing');

            const address = await tab.evaluate('document.querySelector("[data-qr-logo]").dataset.qrLogo');
            const answer = await tab.evaluate(`fetch(${JSON.stringify(address)}).then((r) => ({ status: r.status, type: r.headers.get('content-type') }))`);

            assert.match(address, /\/qr-codes\/\d+\/logo/);
            assert.deepEqual(answer, { status: 200, type: 'image/png' });
        });

        it('opens an edit showing the saved logo, and removing it takes it off the code', async () => {
            const id = (await tab.evaluate('location.pathname')).split('/').pop();

            await tab.open(`${browser.origin}/admin/qr-codes/${id}/edit`);
            await tab.until(() => tab.evaluate('!!document.querySelector(".eq-studio [data-look]")'), 'the editor');
            await tab.until(async () => /<image[^>]+href="[^"]*\/logo/.test((await previewSvg()) ?? ''), 'the saved logo in the preview');

            assert.equal(await tab.evaluate('document.querySelector("[data-logo-remove]").hidden'), false);

            await tab.click('[data-logo-remove]');
            assert.equal(await logoOf(), null);
            await submit();
            await tab.until(() => tab.evaluate('document.body.innerText.includes("Saved")'), 'the save to be confirmed');
            await tab.open(`${browser.origin}/admin/qr-codes/${id}`);
            await tab.until(() => tab.evaluate('!!document.querySelector("[data-qr-design]")'), 'the View page drawing');

            assert.equal(await tab.evaluate('document.querySelector("[data-qr-logo]")'), null);
        });

        it('says so, and leaves no logo on the code, when the picture is not one', async () => {
            await openCreate();
            writeFileSync(PICTURE.replace('logo.png', 'notes.png'), 'plain text, not a picture');
            await tab.setFiles('[data-logo-file]', [PICTURE.replace('logo.png', 'notes.png')]);
            await tab.until(async () => /PNG, JPG or WebP/.test(await message()), 'a reason');

            assert.equal(await logoOf(), null);
            assert.equal(await tab.evaluate('document.querySelector("[data-logo-settings]").hidden'), true);
        });
    });

    describe('the layout', () => {
        before(async () => {
            await tab.open(`${browser.origin}/admin/qr-codes/1/edit`);
            await tab.until(() => tab.evaluate('!!document.querySelector(".eq-studio [data-look]")'), 'the editor');
        });

        const rect = (selector) => tab.evaluate(`(() => { const r = document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right }; })()`);

        it('puts the preview beside the controls on a wide screen and keeps it in view', async () => {
            await tab.setWidth(1400, 700);
            const preview = await rect('.eq-studio-aside');
            const cards = await rect('.eq-studio-cards');

            assert.ok(preview.right <= cards.left + 1, 'the preview sits beside the controls');

            await tab.evaluate('window.scrollTo(0, 900)');
            await tab.until(async () => (await rect('.eq-studio-aside')).top >= 0 && (await rect('.eq-studio-aside')).top < 300, 'the preview to stay in view');
        });

        it('stacks the preview above the controls on a narrow screen without scrolling sideways', async () => {
            await tab.setWidth(500, 900);
            await tab.evaluate('window.scrollTo(0, 0)');
            const preview = await rect('.eq-studio-aside');
            const cards = await rect('.eq-studio-cards');

            assert.ok(preview.bottom <= cards.top + 1, 'the preview sits above the controls');
            assert.equal(await tab.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
        });

        it('keeps the preview light in dark mode while the controls follow the panel', async () => {
            await tab.setWidth(1400, 900);
            await tab.evaluate('document.documentElement.classList.add("dark")');

            const surround = await tab.evaluate('getComputedStyle(document.querySelector(".eq-studio-preview")).backgroundColor');
            const card = await tab.evaluate('getComputedStyle(document.querySelector(".eq-studio-card")).backgroundColor');
            const channels = (colour) => colour.match(/\d+/g).slice(0, 3).map(Number);

            assert.ok(Math.min(...channels(surround)) > 200, `the preview surround is light (${surround})`);
            assert.ok(Math.max(...channels(card)) < 80, `the controls are dark (${card})`);
            assert.deepEqual(ownExceptions(), []);
        });
    });
});
