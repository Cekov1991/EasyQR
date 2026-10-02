import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, before, describe, it } from 'node:test';
import { startBrowser, unavailable, wait } from './browser.mjs';

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
const LINK = 'https://example.com/a-private-menu-nobody-should-see';
const skip = unavailable() ?? false;

if (skip) {
    console.log(`# browser tests skipped: ${skip}`);
}

describe('the homepage in a real browser', { skip }, () => {
    let browser;

    before(async () => {
        browser = await startBrowser();
    });

    after(async () => {
        await browser?.stop();
    });

    async function homepage({ before: setup = '', width } = {}) {
        const tab = await browser.tab();

        if (width) {
            await tab.setWidth(width);
        }

        if (setup) {
            await tab.beforeLoad(setup);
        }

        await tab.open(`${browser.origin}/`);

        return tab;
    }

    async function typeLink(tab, link = LINK) {
        await tab.type('#static-url', link);
        await tab.until(() => tab.evaluate('!document.getElementById("static-result").hidden'), 'the code to be drawn');
    }

    async function toDownloadStep(tab) {
        await tab.click('#static-next');
        await tab.click('#static-next');
    }

    const offerIsOpen = (tab) => tab.evaluate('document.getElementById("static-offer").open');

    describe('the editor', () => {
        const text = (tab, selector) => tab.evaluate(`document.querySelector(${JSON.stringify(selector)}).textContent.trim()`);

        it('opens with only the link field, in the hero, and shows the editor beneath it once a link is valid', async () => {
            const tab = await homepage();

            assert.equal(await tab.evaluate('document.getElementById("static-result").hidden'), true);
            assert.match(await text(tab, '#static-error'), /appears here as soon as you type a link/);

            await typeLink(tab);

            const heroAboveEditor = await tab.evaluate(`
                document.getElementById('static-url').getBoundingClientRect().bottom
                    <= document.getElementById('static-result').getBoundingClientRect().top
            `);

            assert.equal(heroAboveEditor, true);
            assert.equal(await text(tab, '#static-error'), '');
        });

        it('draws nothing and says why for a link that is not valid', async () => {
            const tab = await homepage();

            await tab.type('#static-url', 'not a link');
            await tab.until(async () => (await text(tab, '#static-error')) !== '', 'the message');

            assert.equal(await tab.evaluate('document.getElementById("static-result").hidden'), true);
        });

        it('walks Look, Logo, Download in that order, with the current step announced', async () => {
            const tab = await homepage();

            await typeLink(tab);

            const steps = () => tab.evaluate(`Array.from(document.querySelectorAll('[data-step]:is(button)'), (b) => b.dataset.step + ':' + b.getAttribute('aria-current'))`);

            assert.deepEqual(await steps(), ['look:step', 'logo:false', 'download:false']);
            await tab.click('#static-next');
            assert.deepEqual(await steps(), ['look:false', 'logo:step', 'download:false']);
            await tab.click('#static-next');
            assert.deepEqual(await steps(), ['look:false', 'logo:false', 'download:step']);
            await tab.click('#static-back');
            assert.deepEqual(await steps(), ['look:false', 'logo:step', 'download:false']);
        });

        it('keeps the preview in view and labelled at every step', async () => {
            const tab = await homepage();

            await typeLink(tab);

            for (const step of ['look', 'logo', 'download']) {
                await tab.click(`button[data-step="${step}"]`);
                assert.equal(await tab.evaluate(`document.getElementById('static-preview').getBoundingClientRect().height > 100`), true, step);
            }

            assert.equal(await tab.evaluate('document.getElementById("static-preview").getAttribute("aria-label")'), 'Your QR code');
        });

        it('redraws as the link changes', async () => {
            const tab = await homepage();
            await typeLink(tab, 'https://a.example');
            const before = await tab.evaluate('document.getElementById("static-preview").innerHTML');
            await tab.type('#static-url', '/a-much-longer-path-that-changes-the-pattern');
            await tab.until(async () => (await tab.evaluate('document.getElementById("static-preview").innerHTML')) !== before, 'a redraw');
        });

        it('starts on the Rounded look, and calls the design Custom once a setting differs', async () => {
            const tab = await homepage();

            await typeLink(tab);
            assert.equal(await text(tab, '#static-look-name'), 'Rounded');
            assert.equal(await tab.evaluate(`document.querySelector('#static-looks [aria-pressed="true"]').textContent.trim()`), 'Rounded');

            await tab.evaluate(`document.querySelector('#static-looks .eq-look:nth-child(2)').click()`);
            assert.equal(await text(tab, '#static-look-name'), 'Ink');

            await tab.evaluate(`document.querySelector('[data-setting="eye"][data-value="rounded"]').click()`);
            assert.equal(await text(tab, '#static-look-name'), 'Custom');
            assert.equal(await tab.evaluate(`document.querySelectorAll('#static-looks [aria-pressed="true"]').length`), 0);
        });

        it('offers every option as a named control that states whether it is selected', async () => {
            const tab = await homepage();

            await typeLink(tab);

            const unnamed = await tab.evaluate(`Array.from(document.querySelectorAll('[data-setting], [data-colour-swatch]'))
                .filter((button) => !(button.getAttribute('aria-label') || button.textContent.trim()) || !['true', 'false'].includes(button.getAttribute('aria-pressed')))
                .length`);
            const count = await tab.evaluate(`document.querySelectorAll('[data-setting], [data-colour-swatch]').length`);

            assert.equal(unnamed, 0);
            assert.ok(count > 20);
            assert.equal(await tab.evaluate(`document.querySelector('[data-setting="dot"][data-value="fluid"]').getAttribute('aria-pressed')`), 'true');
            assert.equal(await tab.evaluate(`Array.from(document.querySelectorAll('input[type="range"], input[type="text"].eq-input')).every((field) => document.querySelector('label[for="' + field.id + '"]'))`), true);
        });

        it('offers PNG at four sizes, 1024 first, and SVG', async () => {
            const tab = await homepage();

            await typeLink(tab);

            assert.deepEqual(await tab.evaluate(`Array.from(document.getElementById('static-png-size').options, (o) => o.value)`), ['512', '1024', '2048', '4096']);
            assert.equal(await tab.evaluate('document.getElementById("static-png-size").value'), '1024');
            assert.equal(await text(tab, '#static-download-png'), 'Download PNG');
            assert.equal(await text(tab, '#static-download-svg'), 'Download SVG');
        });

        it('states the reason and switches downloading off when the colours cannot scan, and warns when they might not', async () => {
            const tab = await homepage();
            const setCode = (colour) => tab.evaluate(`(() => {
                const field = document.querySelector('[data-colour-hex="codeColor"]');
                field.value = ${JSON.stringify(colour)};
                field.dispatchEvent(new Event('input', { bubbles: true }));
            })()`);

            await typeLink(tab);
            assert.equal(await tab.evaluate('document.getElementById("static-warnings").children.length'), 0);

            await setCode('#808080');
            assert.match(await text(tab, '#static-warnings'), /Low contrast/);
            assert.equal(await tab.evaluate('document.getElementById("static-download-png").disabled'), false);

            await setCode('#eeeeee');
            assert.match(await text(tab, '#static-blocked'), /too close in colour/);
            assert.equal(await tab.evaluate('document.getElementById("static-blocked").hidden'), false);
            assert.equal(await tab.evaluate('document.getElementById("static-download-png").disabled'), true);
            assert.equal(await tab.evaluate('document.getElementById("static-download-svg").disabled'), true);
        });

        it('lets the frame text be edited only for the frames that have text', async () => {
            const tab = await homepage();
            const textField = () => tab.evaluate('document.querySelector("[data-frame-text-field]").hidden');

            await typeLink(tab);
            assert.equal(await textField(), true);

            await tab.evaluate(`document.querySelector('[data-setting="frame"][data-value="label"]').click()`);
            assert.equal(await textField(), false);
            assert.equal(await tab.evaluate('document.querySelector("[data-frame-text]").maxLength'), 18);

            await tab.evaluate(`document.querySelector('[data-setting="frame"][data-value="border"]').click()`);
            assert.equal(await textField(), true);
        });

        it('skips the logo step without a logo, and keeps a local logo on the device', async () => {
            const tab = await homepage();
            const directory = mkdtempSync(join(tmpdir(), 'eq-logo-'));
            const png = join(directory, 'logo.png');

            writeFileSync(png, PNG);
            await typeLink(tab);
            await tab.click('#static-next');
            assert.equal(await text(tab, '#static-next'), 'Skip');

            const requestsBefore = tab.requests.length;

            await tab.setFiles('[data-logo-file]', [png]);
            await tab.until(() => tab.evaluate('document.querySelector("[data-logo-thumb] img") !== null'), 'the logo to be read');
            assert.equal(await text(tab, '#static-next'), 'Next');
            assert.equal(await tab.evaluate('document.querySelector("[data-logo-settings]").hidden'), false);

            await tab.click('#static-next');
            await tab.click('#static-download-svg');
            await wait(700);

            const newRequests = tab.requests.slice(requestsBefore).filter((request) => !request.url.startsWith('data:'));

            assert.ok(newRequests.every((request) => request.method === 'POST' && request.url.endsWith('/events')), JSON.stringify(newRequests.map((request) => request.url)));
            assert.ok(newRequests.every((request) => !request.body.includes('iVBOR')));
            rmSync(directory, { recursive: true, force: true });
        });

        it('refuses an SVG logo and says which pictures it takes', async () => {
            const tab = await homepage();
            const directory = mkdtempSync(join(tmpdir(), 'eq-logo-'));
            const svg = join(directory, 'logo.svg');

            writeFileSync(svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
            await typeLink(tab);
            await tab.click('#static-next');
            await tab.setFiles('[data-logo-file]', [svg]);
            await tab.until(async () => (await text(tab, '[data-logo-message]')) !== '', 'the refusal');

            assert.match(await text(tab, '[data-logo-message]'), /PNG, JPG or WebP/);
            assert.equal(await tab.evaluate('document.querySelector("[data-logo-thumb] img")'), null);
            assert.equal(await tab.evaluate('document.querySelector("[data-logo-file]").accept.includes("svg")'), false);
            rmSync(directory, { recursive: true, force: true });
        });
    });

    describe('frame text keeps its font', () => {
        async function frameText(tab, frame) {
            return tab.evaluate(`(async () => {
                const design = Object.assign(QrRenderer.defaultDesign(), { frame: ${JSON.stringify(frame)}, frameText: 'SCAN ME' });
                const embedded = QrRenderer.render('https://example.com', design, { pixelWidth: 600 }).svg;
                const withoutFont = embedded.slice(0, embedded.indexOf('<style>')) + embedded.slice(embedded.indexOf('</style>') + 8);
                const plain = withoutFont.split("'EQ Manrope', Manrope, ").join('');
                const draw = (svg) => new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = image.naturalWidth;
                        canvas.height = image.naturalHeight;
                        const context = canvas.getContext('2d');
                        context.fillStyle = '#fff';
                        context.fillRect(0, 0, canvas.width, canvas.height);
                        context.drawImage(image, 0, 0);
                        resolve(context.getImageData(0, 0, canvas.width, canvas.height).data);
                    };
                    image.onerror = reject;
                    image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
                });
                const a = await draw(embedded);
                const b = await draw(plain);
                let differing = 0;
                for (let i = 0; i < a.length; i += 4) {
                    if (Math.abs(a[i] - b[i]) > 40) { differing++; }
                }
                return { differing, embedded: embedded.length, plain: plain.length };
            })()`);
        }

        for (const frame of ['label', 'badge']) {
            it(`a ${frame} frame drawn through an image and canvas, as the PNG is, shows the embedded font and not the fallback`, async () => {
                const tab = await homepage();
                const { differing, embedded, plain } = await frameText(tab, frame);

                assert.ok(embedded > plain + 10000, 'the font is inside the SVG');
                assert.ok(differing > 500, `the embedded font changes the letters (${differing} pixels differ)`);
            });
        }
    });

    describe('reporting that a code was made', () => {
        it('reports nothing on load, nothing for a link that is not valid, and once for the first drawn code', async () => {
            const tab = await homepage();

            await wait(300);
            assert.deepEqual(tab.eventNames(), []);

            await tab.type('#static-url', 'not a link');
            await wait(900);
            assert.deepEqual(tab.eventNames(), []);

            await tab.evaluate('document.getElementById("static-url").value = ""');
            await typeLink(tab);
            await tab.type('#static-url', '/more');
            await tab.type('#static-url', '?and=more');
            await wait(300);

            assert.deepEqual(tab.eventNames(), ['static_qr_generated']);
            assert.equal(tab.events()[0].page, 'home');
        });

        it('is accepted by the server for every event the page reports', async () => {
            const tab = await homepage();

            await typeLink(tab);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await tab.until(() => tab.eventNames().includes('offer_shown'), 'the offer to be counted');
            await tab.click('#static-offer-no');
            await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');
            await wait(300);

            const reports = tab.requests.filter((request) => request.method === 'POST' && request.url.endsWith('/events'));

            assert.deepEqual(tab.eventNames(), ['static_qr_generated', 'qr_downloaded', 'offer_shown', 'offer_dismissed']);
            assert.deepEqual(reports.map((request) => request.status), [204, 204, 204, 204]);
        });

        it('reports again on a new page view, once more', async () => {
            const first = await homepage();
            await typeLink(first);
            const second = await homepage();
            await typeLink(second);

            assert.deepEqual(first.eventNames(), ['static_qr_generated']);
            assert.deepEqual(second.eventNames(), ['static_qr_generated']);
        });

        it('never sends the link anywhere, not in a URL and not in a body', async () => {
            const tab = await homepage();

            await typeLink(tab);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await wait(900);

            const sent = tab.requests.filter((request) => request.method !== 'GET' || request.url.includes('menu'));

            assert.ok(sent.length >= 2, 'the events were sent');

            for (const request of tab.requests) {
                assert.ok(!decodeURIComponent(request.url).includes('a-private-menu'), `url ${request.url}`);
                assert.ok(!request.body.includes('a-private-menu'), `body of ${request.url}`);
            }
        });
    });

    describe('downloading', () => {
        it('reports a PNG download and opens the offer a beat later', async () => {
            const tab = await homepage();

            await typeLink(tab);
            await toDownloadStep(tab);
            assert.equal(await offerIsOpen(tab), false);

            await tab.click('#static-download-png');
            await tab.until(() => tab.eventNames().includes('qr_downloaded'), 'the download to be reported');

            assert.equal(tab.events().find((event) => event.event === 'qr_downloaded').format, 'png');
            assert.equal(await offerIsOpen(tab), false, 'not in the same tick as the download');

            await tab.until(() => offerIsOpen(tab), 'the offer to open');
            await tab.until(() => tab.eventNames().includes('offer_shown'), 'the offer to be counted');
        });

        it('reports an SVG download and opens the offer too', async () => {
            const tab = await homepage();

            await typeLink(tab);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await tab.until(() => tab.eventNames().includes('qr_downloaded'), 'the download to be reported');

            assert.equal(tab.events().find((event) => event.event === 'qr_downloaded').format, 'svg');
            await tab.until(() => offerIsOpen(tab), 'the offer to open');
        });

        it('does not report or open anything while the design cannot scan', async () => {
            const tab = await homepage();

            await typeLink(tab);
            await tab.evaluate(`(() => {
                const field = document.querySelector('[data-colour-hex="codeColor"]');
                field.value = '#eeeeee';
                field.dispatchEvent(new Event('input', { bubbles: true }));
            })()`);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await wait(900);

            assert.ok(!tab.eventNames().includes('qr_downloaded'));
            assert.equal(await offerIsOpen(tab), false);
        });
    });

    describe('the offer', () => {
        async function openOffer(tab) {
            await typeLink(tab);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await tab.until(() => offerIsOpen(tab), 'the offer to open');
        }

        it('counts a dismissal once, however it is closed, and is not shown again', async () => {
            const tab = await homepage();

            await openOffer(tab);
            await tab.click('#static-offer-no');
            await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');
            await tab.click('#static-download-svg');
            await wait(1000);

            assert.equal(await offerIsOpen(tab), false);
            assert.equal(tab.eventNames().filter((name) => name === 'offer_dismissed').length, 1);
            assert.equal(tab.eventNames().filter((name) => name === 'offer_shown').length, 1);
        });

        it('remembers the refusal in the browser, so a reload does not ask again', async () => {
            const tab = await homepage();

            await openOffer(tab);
            await tab.click('#static-offer-dismiss');
            await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');
            await tab.open(`${browser.origin}/`);
            await typeLink(tab);
            await toDownloadStep(tab);
            await tab.click('#static-download-svg');
            await wait(1000);

            assert.equal(await offerIsOpen(tab), false);
        });

        it('remembers the refusal with a plain value in local storage and sets no cookie', async () => {
            const tab = await homepage();
            const cookieNames = () => tab.evaluate('document.cookie.split("; ").map((c) => c.split("=")[0]).filter(Boolean).sort()');
            const cookiesBefore = await cookieNames();

            await openOffer(tab);
            await tab.click('#static-offer-no');
            await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');

            assert.equal(await tab.evaluate('localStorage.getItem("eq.offer.dismissed")'), '1');
            assert.deepEqual(await cookieNames(), cookiesBefore);
        });

        it('keeps the refusal when reporting it throws', async () => {
            const tab = await homepage({ before: 'window.fetch = () => { throw new Error("patched fetch"); };' });

            await openOffer(tab);
            await tab.click('#static-offer-no');
            await tab.click('#static-download-svg');
            await wait(1000);

            assert.equal(await offerIsOpen(tab), false);
            assert.equal(await tab.evaluate('localStorage.getItem("eq.offer.dismissed")'), '1');
            assert.deepEqual(tab.exceptions, []);
        });

        it('keeps the refusal for the page view when storage is unavailable', async () => {
            const tab = await homepage({
                before: `
                    Storage.prototype.setItem = () => { throw new Error('storage is off'); };
                    Storage.prototype.getItem = () => { throw new Error('storage is off'); };
                `,
            });

            await openOffer(tab);
            await tab.click('#static-offer-no');
            await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');
            await tab.click('#static-download-svg');
            await wait(1000);

            assert.equal(await offerIsOpen(tab), false);
            assert.deepEqual(tab.exceptions, []);
        });

        it('counts taking the offer as a click, not as a dismissal', async () => {
            const tab = await homepage();

            await openOffer(tab);
            await tab.evaluate(`(() => {
                const cta = document.getElementById('static-offer-cta');
                cta.addEventListener('click', (event) => event.preventDefault());
                cta.click();
                document.getElementById('static-offer').close();
            })()`);
            await tab.until(() => tab.eventNames().includes('offer_clicked'), 'the click to be counted');
            await wait(300);

            assert.ok(!tab.eventNames().includes('offer_dismissed'));
        });

        it('ships closed and opens as a modal labelled by its own heading', async () => {
            const tab = await homepage();

            assert.equal(await offerIsOpen(tab), false);
            assert.equal(await tab.evaluate(`document.getElementById('static-offer').matches(':modal')`), false);
            assert.match(
                await tab.evaluate(`document.getElementById(document.getElementById('static-offer').getAttribute('aria-labelledby')).textContent`),
                /That code can never be changed/,
            );

            await openOffer(tab);

            assert.equal(await tab.evaluate(`document.getElementById('static-offer').matches(':modal')`), true);
        });

        it('counts one dismissal whether it is closed by Escape, the backdrop or the cross', async () => {
            for (const close of [
                (tab) => tab.key('Escape'),
                (tab) => tab.evaluate(`document.getElementById('static-offer').click()`),
                (tab) => tab.click('#static-offer-dismiss'),
            ]) {
                const tab = await homepage();

                await openOffer(tab);
                await close(tab);
                await tab.until(() => tab.eventNames().includes('offer_dismissed'), 'the dismissal to be counted');
                await wait(200);

                assert.equal(tab.eventNames().filter((name) => name === 'offer_dismissed').length, 1);
                assert.equal(await offerIsOpen(tab), false);
            }
        });

        it('carries both register links with their refs', async () => {
            const tab = await homepage();

            const links = await tab.evaluate(`Array.from(document.querySelectorAll('a[href*="register"]'), (a) => a.href)`);

            assert.ok(links.some((href) => href.includes('ref=static-offer')), links.join('\n'));
            assert.ok(links.some((href) => href.includes('ref=static-inline')), links.join('\n'));
        });
    });

    describe('copy and share', () => {
        const none = `
            delete Navigator.prototype.share;
            delete Navigator.prototype.canShare;
            Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
            window.ClipboardItem = undefined;
        `;
        const both = `
            Object.defineProperty(navigator, 'share', { value: async () => {}, configurable: true });
            Object.defineProperty(navigator, 'canShare', { value: () => true, configurable: true });
            Object.defineProperty(navigator, 'clipboard', { value: { write: async () => {} }, configurable: true });
            window.ClipboardItem = function ClipboardItem() {};
        `;
        const hidden = (tab, id) => tab.evaluate(`document.getElementById(${JSON.stringify(id)}).hidden`);

        it('hides both buttons where the browser can do neither', async () => {
            const tab = await homepage({ before: none });

            await typeLink(tab);
            await toDownloadStep(tab);

            assert.equal(await hidden(tab, 'static-copy'), true);
            assert.equal(await hidden(tab, 'static-share'), true);
        });

        it('hides share but not copy where only the clipboard works', async () => {
            const tab = await homepage({ before: none + `
                Object.defineProperty(navigator, 'clipboard', { value: { write: async () => {} }, configurable: true });
                window.ClipboardItem = function ClipboardItem() {};
            ` });

            await typeLink(tab);

            assert.equal(await hidden(tab, 'static-copy'), false);
            assert.equal(await hidden(tab, 'static-share'), true);
        });

        it('shows both where the browser can do both', async () => {
            const tab = await homepage({ before: both });

            await typeLink(tab);
            await toDownloadStep(tab);

            assert.equal(await hidden(tab, 'static-copy'), false);
            assert.equal(await hidden(tab, 'static-share'), false);
        });

        it('hides share where the browser cannot share files', async () => {
            const tab = await homepage({ before: both + 'Object.defineProperty(navigator, "canShare", { value: () => false, configurable: true });' });

            await typeLink(tab);

            assert.equal(await hidden(tab, 'static-share'), true);
        });
    });

    describe('on a phone', () => {
        const overflow = (tab) => tab.evaluate('document.documentElement.scrollWidth - document.documentElement.clientWidth');

        it('has no horizontal scroll at 375px once a link is typed, at every step', async () => {
            const tab = await homepage({ width: 375 });

            assert.ok((await overflow(tab)) <= 0, 'before typing');

            await typeLink(tab);
            assert.equal(await tab.evaluate('window.innerWidth'), 375);
            assert.ok((await overflow(tab)) <= 0, 'look step');

            await tab.evaluate('document.querySelector(".eq-more").open = true');
            await tab.evaluate(`document.querySelector('[data-setting="frame"][data-value="badge"]').click()`);
            assert.ok((await overflow(tab)) <= 0, 'look step with every option open');

            await tab.click('#static-next');
            await tab.evaluate(`document.querySelector('[data-logo-settings]').hidden = false`);
            assert.ok((await overflow(tab)) <= 0, 'logo step');

            await tab.click('#static-next');
            assert.ok((await overflow(tab)) <= 0, 'download step');
        });
    });
});
