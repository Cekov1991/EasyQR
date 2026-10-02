import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, it } from 'node:test';
import { Resvg } from '@resvg/resvg-js';
import { CONTENTS, decode, design, download, drawing, LOGO, logo, options, renderer, SHORT_URL } from './support.mjs';

const INK = design({ ...renderer.LOOKS.ink, label: undefined, frame: 'badge' });
const ROUNDED_SVG = renderer.render(SHORT_URL, renderer.defaultDesign()).svg;

/** Reads a stored ZIP the way an unzip tool would: from the central directory, checking each CRC. */
function readZip(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const end = bytes.length - 22;

    assert.equal(view.getUint32(end, true), 0x06054b50, 'end of central directory');

    const count = view.getUint16(end + 10, true);
    let at = view.getUint32(end + 16, true);
    const files = {};

    for (let i = 0; i < count; i++) {
        assert.equal(view.getUint32(at, true), 0x02014b50, 'central directory entry');

        const crc = view.getUint32(at + 16, true);
        const size = view.getUint32(at + 24, true);
        const nameLength = view.getUint16(at + 28, true);
        const local = view.getUint32(at + 42, true);
        const name = new TextDecoder().decode(bytes.subarray(at + 46, at + 46 + nameLength));
        const start = local + 30 + view.getUint16(local + 26, true) + view.getUint16(local + 28, true);
        const data = bytes.slice(start, start + size);

        assert.equal(download.crc32(data), crc, `${name} checksum`);
        files[name] = data;
        at += 46 + nameLength;
    }

    return files;
}

const rasterise = async (svg, width) => new Uint8Array(new Resvg(svg, { fitTo: { mode: 'width', value: width }, background: 'white' }).render().asPng());

describe('a saved code is drawn from its Design', () => {
    it('draws the Design it is given, and the drawing scans as its content', () => {
        for (const [name, content] of Object.entries(CONTENTS)) {
            const svg = drawing.draw(content, JSON.stringify(INK));

            assert.equal(decode(svg, 600), content, name);
        }
    });

    it('accepts the Design as an object as well as the JSON a page carries', () => {
        assert.equal(drawing.draw(SHORT_URL, INK), drawing.draw(SHORT_URL, JSON.stringify(INK)));
    });

    it('draws a code with no Design in the Rounded Look', () => {
        for (const missing of [undefined, null, '', 'null']) {
            assert.equal(drawing.draw(SHORT_URL, missing), ROUNDED_SVG, String(missing));
        }
    });

    it('draws a code saved with anything but a version 1 Design in the Rounded Look', () => {
        for (const old of [{ style: 'dot', color: '#ff0000' }, { ...INK, version: 2 }, '{not json', 42]) {
            assert.equal(drawing.draw(SHORT_URL, old), ROUNDED_SVG, JSON.stringify(old));
        }
    });

    it('draws nothing, rather than throwing, for content it cannot encode', () => {
        assert.equal(drawing.draw('', INK), '');
        assert.equal(drawing.draw('x'.repeat(5000), INK), '');
    });
});

describe('the ZIP of all formats', () => {
    it('is a valid archive holding each file byte for byte', () => {
        const entries = [
            { name: 'a.svg', data: new TextEncoder().encode('<svg/>') },
            { name: 'b-512px.png', data: new Uint8Array([137, 80, 78, 71, 0, 255, 128]) },
            { name: 'empty.txt', data: new Uint8Array() },
        ];

        const files = readZip(download.zip(entries));

        assert.deepEqual(Object.keys(files), entries.map((entry) => entry.name));
        entries.forEach((entry) => assert.deepEqual(files[entry.name], entry.data));
    });

    it('is read by a real unzip tool', { skip: spawnSync('unzip', ['-v']).status !== 0 && 'unzip is not installed' }, async () => {
        const entries = await download.bundleEntries(SHORT_URL, INK, 'poster', options.png.sizes, rasterise);
        const directory = mkdtempSync(join(tmpdir(), 'qr-zip-'));
        const path = join(directory, 'all.zip');

        writeFileSync(path, download.zip(entries));
        const result = spawnSync('unzip', ['-t', path], { encoding: 'utf8' });
        rmSync(directory, { recursive: true });

        assert.equal(result.status, 0, result.stdout + result.stderr);
        assert.match(result.stdout, /No errors detected/);
    });
});

describe('every format of a code', () => {
    it('is the SVG and a PNG at each size, named after the code', async () => {
        const entries = await download.bundleEntries(SHORT_URL, INK, 'poster', options.png.sizes, rasterise);

        assert.deepEqual(entries.map((entry) => entry.name), [
            'poster.svg', ...options.png.sizes.map((width) => `poster-${width}px.png`),
        ]);
    });

    it('offers the four PNG sizes, 1024 by default', () => {
        assert.deepEqual(options.png.sizes, [512, 1024, 2048, 4096]);
        assert.equal(options.png.default, 1024);
    });

    it('has an SVG that scans as the content and states its dimensions', async () => {
        const [svg] = await download.bundleEntries(SHORT_URL, INK, 'poster', [], rasterise);
        const text = new TextDecoder().decode(svg.data);

        assert.match(text, /<svg[^>]* width="\d+" height="\d+"/);
        assert.equal(decode(text, 600), SHORT_URL);
    });

    it('has PNGs of the requested width that follow the frame\'s aspect ratio', async () => {
        const entries = await download.bundleEntries(SHORT_URL, INK, 'poster', [512, 1024], rasterise);
        const shape = renderer.render(SHORT_URL, INK);

        entries.filter((entry) => entry.name.endsWith('.png')).forEach((entry, index) => {
            const view = new DataView(entry.data.buffer, entry.data.byteOffset);
            const width = [512, 1024][index];

            assert.equal(view.getUint32(16), width);
            assert.ok(Math.abs(view.getUint32(20) - width * shape.height / shape.width) <= 1);
        });
    });

    it('draws a code with no Design in the Rounded Look', async () => {
        const [svg] = await download.bundleEntries(SHORT_URL, undefined, 'poster', [], rasterise);

        assert.equal(decode(new TextDecoder().decode(svg.data), 600), SHORT_URL);
    });
});

/** How many pixels of a rasterised drawing are the logo's red. */
function redPixels(svg, width) {
    const { pixels } = new Resvg(svg, { fitTo: { mode: 'width', value: width }, background: 'white' }).render();
    let red = 0;

    for (let i = 0; i < pixels.length; i += 4) {
        if (pixels[i] > 200 && pixels[i + 1] < 70 && pixels[i + 2] < 70) {
            red++;
        }
    }

    return red;
}

const WITH_LOGO = design({ logo: logo({ shape: 'square', backing: true, clearSpace: true }) });
const LOGO_URL = 'https://app.example/qr-codes/7/logo?v=abc';

describe('a saved code with a logo', () => {
    it('draws the logo from the address a page gives it, and still scans', () => {
        const svg = drawing.draw(SHORT_URL, WITH_LOGO, { logoSrc: LOGO });

        assert.ok(redPixels(svg, 600) > 100, 'the logo is drawn');
        assert.equal(decode(svg, 600), SHORT_URL);
    });

    it('draws the logo from the data-qr-logo address of the element it paints', () => {
        const element = { dataset: { qrContent: SHORT_URL, qrDesign: JSON.stringify(WITH_LOGO), qrLogo: LOGO }, innerHTML: '' };

        drawing.paint(element);

        assert.ok(redPixels(element.innerHTML, 600) > 100);
    });

    it('draws no logo image when the page gives none', () => {
        const element = { dataset: { qrContent: SHORT_URL, qrDesign: JSON.stringify(WITH_LOGO) }, innerHTML: '' };

        drawing.paint(element);

        assert.equal(redPixels(element.innerHTML, 600), 0);
        assert.ok(element.innerHTML.length > 0);
    });
});

describe('downloads of a code with a logo', () => {
    it('turns the logo route address into a picture that travels inside a file', async () => {
        const bytes = Buffer.from('logo-bytes');
        const asked = [];
        const fetcher = async (url, init) => {
            asked.push([url, init?.credentials]);

            return { ok: true, blob: async () => new Blob([bytes], { type: 'image/png' }) };
        };

        const inlined = await download.inlineLogo(LOGO_URL, fetcher);

        assert.equal(inlined, `data:image/png;base64,${bytes.toString('base64')}`);
        assert.deepEqual(asked, [[LOGO_URL, 'same-origin']]);
    });

    it('has no picture to inline when there is no logo, or when it cannot be fetched', async () => {
        assert.equal(await download.inlineLogo(null, async () => assert.fail('nothing to fetch')), null);
        assert.equal(await download.inlineLogo('', async () => assert.fail('nothing to fetch')), null);
        await assert.rejects(download.inlineLogo(LOGO_URL, async () => ({ ok: false })));
        await assert.rejects(download.inlineLogo(LOGO_URL, async () => { throw new Error('offline'); }));
    });

    it('puts the logo inside the SVG and in the picture every PNG is drawn from', async () => {
        const drawnFrom = [];
        const spy = async (svg, width) => {
            drawnFrom.push(svg);

            return rasterise(svg, width);
        };

        const [svg] = await download.bundleEntries(SHORT_URL, WITH_LOGO, 'poster', [512, 1024], spy, { logoSrc: LOGO });
        const text = new TextDecoder().decode(svg.data);

        assert.ok(redPixels(text, 600) > 100, 'the SVG carries the logo');
        assert.equal(decode(text, 600), SHORT_URL);
        assert.equal(drawnFrom.length, 2);
        drawnFrom.forEach((source) => assert.ok(redPixels(source, 600) > 100, 'a PNG is drawn with the logo'));
    });
});
