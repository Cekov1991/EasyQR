/**
 * The one QR renderer (ADR-0005). Given what a code encodes and a Design, it
 * returns an SVG string plus facts about the result. Everything that shows or
 * downloads a code draws it through here, so the preview and the printed code
 * cannot disagree.
 *
 * Needs the global `qrcode` from qrcode-generator.js. Classic script, no build
 * step: it also loads in Node, which is how `npm test` proves the output scans.
 */
(function (root) {
    'use strict';

    const UNIT = 10;
    const QUIET_MODULES = 4;
    const MAX_FRAME_TEXT = 18;
    const BLOCK_CONTRAST = 3;
    const WARN_CONTRAST = 4;
    const MAX_LOGO_COVERAGE = 25;

    const LOOKS = Object.freeze({
        rounded: Object.freeze({ label: 'Rounded', dot: 'fluid', corner: 'rounded', eye: 'dot', codeColor: '#171b19', eyeColor: '#348FAD', bgColor: '#ffffff' }),
        ink: Object.freeze({ label: 'Ink', dot: 'square', corner: 'square', eye: 'square', codeColor: '#000000', eyeColor: '#000000', bgColor: '#ffffff' }),
        bloom: Object.freeze({ label: 'Bloom', dot: 'dots', corner: 'circle', eye: 'dot', codeColor: '#2C7790', eyeColor: '#3A9FC0', bgColor: '#F4FAFC' }),
        soft: Object.freeze({ label: 'Soft', dot: 'rounded', corner: 'rounded', eye: 'rounded', codeColor: '#0A0F24', eyeColor: '#348FAD', bgColor: '#D4EBF2' }),
    });

    const LOOK_KEYS = ['dot', 'corner', 'eye', 'codeColor', 'eyeColor', 'bgColor'];

    let drawings = 0;

    function lookSettings(key) {
        const { label, ...settings } = LOOKS[key];

        return settings;
    }

    function defaultDesign() {
        return {
            version: 1,
            ...lookSettings('rounded'),
            frame: 'none',
            frameText: 'SCAN ME',
            logo: null,
        };
    }

    function applyLook(design, key) {
        if (!Object.hasOwn(LOOKS, key)) {
            throw new RangeError(`Unknown look: ${key}`);
        }

        return { ...design, ...lookSettings(key), logo: design.logo ? { ...design.logo } : null };
    }

    function lookOf(design) {
        const match = Object.keys(LOOKS).find((key) => LOOK_KEYS.every(
            (setting) => String(LOOKS[key][setting]).toLowerCase() === String(design[setting]).toLowerCase(),
        ));

        return match ?? 'custom';
    }

    function luminance(hex) {
        const value = String(hex || '#000000').replace('#', '');
        const full = value.length === 3 ? value.split('').map((c) => c + c).join('') : value.padEnd(6, '0');
        const [r, g, b] = [0, 2, 4]
            .map((i) => parseInt(full.slice(i, i + 2), 16) / 255)
            .map((c) => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)));

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    function contrast(a, b) {
        const [la, lb] = [luminance(a), luminance(b)];

        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    /** Share of the code's area the logo hides, in percent: the cleared box with clear space on, else the logo box. */
    function logoCoverage(logo) {
        if (!logo) {
            return 0;
        }

        const edge = logo.clearSpace ? logo.size + 2 * logo.padding : logo.size;

        return (edge * edge) / 100;
    }

    function round(value, places = 1) {
        const factor = 10 ** places;

        return Math.round(value * factor) / factor;
    }

    function check(design) {
        const blockers = [];
        const warnings = [];

        const codeContrast = contrast(design.codeColor, design.bgColor);
        const eyeContrast = contrast(design.eyeColor, design.bgColor);
        const coverage = logoCoverage(design.logo);

        if (codeContrast < BLOCK_CONTRAST) {
            blockers.push({ rule: 'contrast', message: `The code and background are too close in colour (${round(codeContrast)}:1). Scanners need at least ${BLOCK_CONTRAST}:1.` });
        } else if (codeContrast < WARN_CONTRAST) {
            warnings.push({ rule: 'low-contrast', message: `Low contrast (${round(codeContrast)}:1). Some phones will not read it.` });
        }

        if (eyeContrast < BLOCK_CONTRAST) {
            warnings.push({ rule: 'faint-eyes', message: `Corner eyes are faint against the background (${round(eyeContrast)}:1).` });
        }

        if (luminance(design.codeColor) > luminance(design.bgColor)) {
            warnings.push({ rule: 'inverted', message: 'Light code on a dark background. Many older scanners cannot read inverted codes.' });
        }

        if (coverage > MAX_LOGO_COVERAGE) {
            blockers.push({ rule: 'logo-coverage', message: `The logo hides ${round(coverage)}% of the code. Keep it to ${MAX_LOGO_COVERAGE}% or less.` });
        }

        return { blocked: blockers.length > 0, blockers, warnings };
    }

    function roundedRect(x, y, w, h, tl, tr = tl, br = tl, bl = tl) {
        return `M${x + tl} ${y}H${x + w - tr}A${tr} ${tr} 0 0 1 ${x + w} ${y + tr}V${y + h - br}A${br} ${br} 0 0 1 ${x + w - br} ${y + h}H${x + bl}A${bl} ${bl} 0 0 1 ${x} ${y + h - bl}V${y + tl}A${tl} ${tl} 0 0 1 ${x + tl} ${y}Z`;
    }

    function circle(cx, cy, r) {
        return `M${cx - r} ${cy}a${r} ${r} 0 1 0 ${2 * r} 0a${r} ${r} 0 1 0 ${-2 * r} 0Z`;
    }

    function escapeXml(value) {
        return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[c]);
    }

    function buildMatrix(content, errorCorrection) {
        qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
        const matrix = qrcode(0, errorCorrection);
        matrix.addData(content);
        matrix.make();

        return matrix;
    }

    function moduleShapes(design, matrix, isDark) {
        const count = matrix.getModuleCount();
        const quiet = QUIET_MODULES * UNIT;
        let path = '';

        for (let r = 0; r < count; r++) {
            for (let c = 0; c < count; c++) {
                if (!isDark(r, c)) {
                    continue;
                }

                const x = quiet + c * UNIT;
                const y = quiet + r * UNIT;

                if (design.dot === 'square') {
                    path += `M${x} ${y}h${UNIT}v${UNIT}h${-UNIT}Z`;
                } else if (design.dot === 'dots') {
                    path += circle(x + UNIT / 2, y + UNIT / 2, UNIT * 0.45);
                } else if (design.dot === 'rounded') {
                    path += roundedRect(x + 0.5, y + 0.5, UNIT - 1, UNIT - 1, UNIT * 0.3);
                } else {
                    const up = isDark(r - 1, c);
                    const down = isDark(r + 1, c);
                    const left = isDark(r, c - 1);
                    const right = isDark(r, c + 1);
                    const radius = UNIT / 2;
                    path += roundedRect(x, y, UNIT, UNIT,
                        !up && !left ? radius : 0, !up && !right ? radius : 0,
                        !down && !right ? radius : 0, !down && !left ? radius : 0);
                }
            }
        }

        return path;
    }

    function finderShapes(design, count) {
        const quiet = QUIET_MODULES * UNIT;
        let rings = '';
        let eyes = '';

        for (const [fr, fc] of [[0, 0], [0, count - 7], [count - 7, 0]]) {
            const x = quiet + fc * UNIT;
            const y = quiet + fr * UNIT;

            if (design.corner === 'circle') {
                rings += circle(x + 3.5 * UNIT, y + 3.5 * UNIT, 3.5 * UNIT) + circle(x + 3.5 * UNIT, y + 3.5 * UNIT, 2.5 * UNIT);
            } else {
                const outer = design.corner === 'rounded' ? 2.2 * UNIT : 0;
                const inner = design.corner === 'rounded' ? 1.4 * UNIT : 0;
                rings += roundedRect(x, y, 7 * UNIT, 7 * UNIT, outer) + roundedRect(x + UNIT, y + UNIT, 5 * UNIT, 5 * UNIT, inner);
            }

            if (design.eye === 'dot') {
                eyes += circle(x + 3.5 * UNIT, y + 3.5 * UNIT, 1.5 * UNIT);
            } else {
                eyes += roundedRect(x + 2 * UNIT, y + 2 * UNIT, 3 * UNIT, 3 * UNIT, design.eye === 'rounded' ? 0.8 * UNIT : 0);
            }
        }

        return { rings, eyes };
    }

    function logoMarkup(design, geometry, logoSrc) {
        const { centre, codeSize } = geometry;
        const edge = codeSize * design.logo.size / 100;
        const clearHalf = edge / 2 + codeSize * design.logo.padding / 100;
        const shape = (origin, size) => (design.logo.shape === 'circle'
            ? circle(centre, centre, size / 2)
            : roundedRect(origin, origin, size, size, design.logo.shape === 'rounded' ? size * 0.22 : 0));
        const clipId = `qr-logo-clip-${++drawings}`;
        const origin = centre - edge / 2;
        let markup = '';

        if (design.logo.backing) {
            markup += `<path d="${shape(centre - clearHalf, clearHalf * 2)}" fill="#ffffff"/>`;
        }

        markup += `<clipPath id="${clipId}"><path d="${shape(origin, edge)}"/></clipPath>`;

        if (logoSrc) {
            markup += `<image href="${escapeXml(logoSrc)}" x="${origin}" y="${origin}" width="${edge}" height="${edge}" preserveAspectRatio="xMidYMid slice" clip-path="url(#${clipId})"/>`;
        }

        return markup;
    }

    function frameMarkup(design, code, size) {
        const text = escapeXml(String(design.frameText ?? '').slice(0, MAX_FRAME_TEXT));
        const font = 'font-family="Manrope, Arial, sans-serif" font-weight="800" letter-spacing="2"';
        const u = UNIT;
        let width = size;
        let height = size;
        let body = code;

        if (design.frame === 'label') {
            height = size + 9 * u;
            body = `<rect width="${width}" height="${height}" fill="${design.bgColor}"/>${code}`
                + `<rect x="${QUIET_MODULES * u}" y="${size - u}" width="${size - 2 * QUIET_MODULES * u}" height="${7 * u}" rx="${1.5 * u}" fill="${design.codeColor}"/>`
                + `<text x="${width / 2}" y="${size + 3.6 * u}" text-anchor="middle" ${font} font-size="${3.4 * u}" fill="${design.bgColor}">${text}</text>`;
        } else if (design.frame === 'border') {
            const pad = 2 * u;
            width = size + 2 * pad;
            height = width;
            body = `<rect width="${width}" height="${height}" rx="${4 * u}" fill="${design.bgColor}"/>`
                + `<rect x="${u}" y="${u}" width="${width - 2 * u}" height="${height - 2 * u}" rx="${3.2 * u}" fill="none" stroke="${design.codeColor}" stroke-width="${0.8 * u}"/>`
                + `<g transform="translate(${pad} ${pad})">${code}</g>`;
        } else if (design.frame === 'badge') {
            const pad = 2.5 * u;
            width = size + 2 * pad;
            height = size + pad + 11 * u;
            body = `<rect width="${width}" height="${height}" rx="${4 * u}" fill="${design.codeColor}"/>`
                + `<rect x="${pad}" y="${pad}" width="${size}" height="${size}" rx="${2.5 * u}" fill="${design.bgColor}"/>`
                + `<g transform="translate(${pad} ${pad})">${code}</g>`
                + `<text x="${width / 2}" y="${size + pad + 7 * u}" text-anchor="middle" ${font} font-size="${4 * u}" fill="${design.bgColor}">${text}</text>`;
        }

        return { width, height, body };
    }

    /**
     * Draws a code.
     *
     * @param {string} content what the code encodes
     * @param {object} design a Design (version 1)
     * @param {{logoSrc?: string|null, pixelWidth?: number}} [options] where the browser can fetch the logo image from; pixelWidth gives the SVG explicit dimensions instead of filling its container
     * @returns {{svg: string, width: number, height: number, modules: number, errorCorrection: string, logoCoverage: number}}
     */
    function render(content, design, options = {}) {
        if (!content) {
            throw new RangeError('Nothing to encode');
        }

        const logo = design.logo;
        const errorCorrection = logo ? 'H' : 'M';
        const matrix = buildMatrix(content, errorCorrection);
        const count = matrix.getModuleCount();
        const quiet = QUIET_MODULES * UNIT;
        const codeSize = count * UNIT;
        const centre = quiet + codeSize / 2;
        const clearHalf = logo ? codeSize * logo.size / 100 / 2 + codeSize * logo.padding / 100 : 0;

        const isFinder = (r, c) => (r < 7 && c < 7) || (r < 7 && c >= count - 7) || (r >= count - 7 && c < 7);
        const isCleared = (r, c) => {
            if (!logo || !logo.clearSpace) {
                return false;
            }
            const x = quiet + c * UNIT;
            const y = quiet + r * UNIT;

            return x + UNIT > centre - clearHalf && x < centre + clearHalf && y + UNIT > centre - clearHalf && y < centre + clearHalf;
        };
        const isDark = (r, c) => r >= 0 && c >= 0 && r < count && c < count
            && matrix.isDark(r, c) && !isFinder(r, c) && !isCleared(r, c);

        const { rings, eyes } = finderShapes(design, count);
        const size = codeSize + 2 * quiet;
        const code = `<rect width="${size}" height="${size}" fill="${design.bgColor}"/>`
            + `<path d="${moduleShapes(design, matrix, isDark)}" fill="${design.codeColor}"/>`
            + `<path d="${rings}" fill="${design.codeColor}" fill-rule="evenodd"/>`
            + `<path d="${eyes}" fill="${design.eyeColor}"/>`
            + (logo ? logoMarkup(design, { centre, codeSize }, options.logoSrc) : '');

        const { width, height, body } = frameMarkup(design, code, size);
        const dimensions = options.pixelWidth
            ? `width="${options.pixelWidth}" height="${Math.round(options.pixelWidth * height / width)}"`
            : 'width="100%" height="100%"';

        return {
            svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" ${dimensions}>${body}</svg>`,
            width,
            height,
            modules: count,
            errorCorrection,
            logoCoverage: logoCoverage(logo),
        };
    }

    root.QrRenderer = Object.freeze({ render, check, contrast, luminance, logoCoverage, defaultDesign, applyLook, lookOf, LOOKS });
})(globalThis);
