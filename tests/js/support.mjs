import { Resvg } from '@resvg/resvg-js';
import jsQR from 'jsqr';
import { readFileSync } from 'node:fs';
import { runInThisContext } from 'node:vm';

for (const file of ['qrcode-generator.js', 'qr-renderer.js', 'qr-design-controls.js']) {
    runInThisContext(readFileSync(new URL(`../../public/js/${file}`, import.meta.url), 'utf8'), { filename: file });
}

export const renderer = globalThis.QrRenderer;
export const controls = globalThis.QrDesignControls;

export const SHORT_URL = 'https://easyqr.example/q/aB3dE5gH';
export const LONG_URL = 'https://www.example.com/restaurants/amsterdam/central/menu?lang=en&table=14&utm_source=qr&utm_medium=print&utm_campaign=autumn-2026&session=0123456789abcdef0123456789abcdef';
export const VCARD = [
    'BEGIN:VCARD', 'VERSION:3.0', 'N:Jansen;Eva;;;', 'FN:Eva Jansen', 'ORG:Jansen Bakkerij',
    'TEL;TYPE=CELL:+31612345678', 'EMAIL:eva@jansen-bakkerij.example', 'URL:https://jansen-bakkerij.example',
    'ADR;TYPE=WORK:;;Keizersgracht 12;Amsterdam;;1015CJ;Netherlands', 'END:VCARD',
].join('\n');
export const CONTENTS = { short: SHORT_URL, long: LONG_URL, vcard: VCARD };

export const LOGO = 'data:image/svg+xml;base64,' + Buffer.from(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
    + '<rect width="100" height="100" fill="#d92d20"/><circle cx="50" cy="50" r="30" fill="#ffffff"/>'
    + '<rect x="40" y="10" width="20" height="80" fill="#233a83"/></svg>',
).toString('base64');

export function design(overrides = {}) {
    return { ...renderer.defaultDesign(), ...overrides };
}

export function logo(overrides = {}) {
    return { path: 'logos/test.png', shape: 'rounded', size: 20, padding: 2, backing: true, clearSpace: true, ...overrides };
}

/**
 * jsQR is an unforgiving decoder: whether it reads a code that every phone reads
 * depends on how many pixels each module gets. So a Design is raster-scanned the
 * way a camera sees it, at several sizes, and counts as scanning when most of them decode.
 */
export const SAMPLE_PIXELS_PER_MODULE = [3, 3.5, 4, 4.5, 5, 5.5, 6];

export function decode(svg, width) {
    const image = new Resvg(svg, { fitTo: { mode: 'width', value: width }, background: 'white' }).render();
    const result = jsQR(new Uint8ClampedArray(image.pixels), image.width, image.height);

    return result ? result.data : null;
}

export function drawn(content, d) {
    return renderer.render(content, d, { logoSrc: d.logo ? LOGO : null });
}

function decodesAt(result, content, pixelsPerModule) {
    return decode(result.svg, Math.round((result.modules + 8) * pixelsPerModule)) === content;
}

export function scansAs(content, d) {
    const result = drawn(content, d);
    const needed = Math.floor(SAMPLE_PIXELS_PER_MODULE.length / 2) + 1;
    let hits = 0;
    let misses = 0;

    for (const pixelsPerModule of SAMPLE_PIXELS_PER_MODULE) {
        if (decodesAt(result, content, pixelsPerModule)) {
            hits++;
        } else {
            misses++;
        }
        if (hits >= needed || misses > SAMPLE_PIXELS_PER_MODULE.length - needed) {
            break;
        }
    }

    return hits >= needed;
}
