import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { controls, design, drawn, options, renderer } from './support.mjs';

describe('colours', () => {
    it('accepts a hex colour with or without the hash, in any case, and short or long', () => {
        assert.equal(controls.normaliseHex('#AbCdEf'), '#abcdef');
        assert.equal(controls.normaliseHex('abcdef'), '#abcdef');
        assert.equal(controls.normaliseHex(' #0f8 '), '#00ff88');
    });

    it('refuses anything that is not a colour', () => {
        for (const value of ['', '#12', '#12345', '#1234567', 'red', '#ggg', null, undefined]) {
            assert.equal(controls.normaliseHex(value), null, String(value));
        }
    });

    it('sets a colour, and keeps the old one when the typed value is not a colour', () => {
        const start = design();

        assert.equal(controls.setColour(start, 'codeColor', '#FF0000').codeColor, '#ff0000');
        assert.equal(controls.setColour(start, 'codeColor', 'nope').codeColor, start.codeColor);
    });

    it('only sets the three colours', () => {
        assert.throws(() => controls.setColour(design(), 'frame', '#ff0000'));
    });
});

describe('shapes and frame', () => {
    it('sets each option to a value in its set', () => {
        const start = design();

        assert.equal(controls.setOption(start, 'dot', 'dots').dot, 'dots');
        assert.equal(controls.setOption(start, 'corner', 'circle').corner, 'circle');
        assert.equal(controls.setOption(start, 'eye', 'rounded').eye, 'rounded');
        assert.equal(controls.setOption(start, 'frame', 'badge').frame, 'badge');
    });

    it('refuses a value outside the set, and does not change the original', () => {
        const start = design();

        assert.throws(() => controls.setOption(start, 'dot', 'triangle'));
        assert.throws(() => controls.setOption(start, 'colour', 'red'));
        assert.equal(controls.setOption(start, 'dot', 'dots') === start, false);
        assert.equal(start.dot, 'fluid');
    });

    it('offers exactly the values the data file lists', () => {
        for (const [setting, { values }] of Object.entries(options.options)) {
            assert.deepEqual([...controls.SETS[setting]], Object.keys(values), setting);
        }
        assert.deepEqual([...controls.COLOURS], Object.keys(options.colours));
    });

    it('accepts every value the data file lists', () => {
        for (const setting of ['dot', 'corner', 'eye', 'frame']) {
            for (const value of Object.keys(options.options[setting].values)) {
                assert.equal(controls.setOption(design(), setting, value)[setting], value);
            }
        }
    });

    it('limits the frame text to the length the data file sets', () => {
        assert.equal(controls.setFrameText(design(), 'x'.repeat(40)).frameText.length, options.frameText.max);
        assert.equal(controls.setFrameText(design(), 'HELLO').frameText, 'HELLO');
    });
});

describe('the logo', () => {
    it('starts at a size and padding that scan, with its backing and clear space', () => {
        const withLogo = controls.addLogo(design());

        assert.ok(withLogo.logo);
        assert.equal(renderer.check(withLogo).blocked, false);
        assert.equal(withLogo.logo.backing, true);
        assert.equal(withLogo.logo.clearSpace, true);
    });

    it('is removed with nothing left behind', () => {
        assert.equal(controls.removeLogo(controls.addLogo(design())).logo, null);
    });

    it('keeps size within 10 to 25 and padding within 0 to 5', () => {
        const withLogo = controls.addLogo(design());

        assert.equal(controls.setLogoSetting(withLogo, 'padding', 99).logo.padding, 5);
        assert.equal(controls.setLogoSetting(withLogo, 'padding', -3).logo.padding, 0);
        assert.equal(controls.setLogoSetting(withLogo, 'size', 3).logo.size, 10);
        assert.equal(controls.setLogoSetting(withLogo, 'size', 90).logo.size, 25 - 2 * withLogo.logo.padding);
    });

    it('lets the size slider reach 25% minus twice the padding', () => {
        const withLogo = controls.addLogo(design());

        for (const padding of [0, 2, 3.5, 5]) {
            const limits = controls.logoLimits(controls.setLogoSetting(withLogo, 'padding', padding));

            assert.equal(limits.size.min, 10);
            assert.equal(limits.size.max, 25 - 2 * padding);
            assert.deepEqual(limits.padding, { min: 0, max: 5 });
        }
    });

    it('pulls the size down when more padding would push the hidden box past 25%', () => {
        const big = controls.setLogoSetting(controls.setLogoSetting(controls.addLogo(design()), 'padding', 0), 'size', 25);
        const padded = controls.setLogoSetting(big, 'padding', 5);

        assert.equal(padded.logo.size, 15);
        assert.equal(renderer.logoCoverage(padded.logo), 25);
        assert.equal(renderer.check(padded).blocked, false);
    });

    it('cannot reach a blocked value through any sequence of control moves', () => {
        let state = controls.addLogo(design());
        const moves = [['size', 25], ['padding', 5], ['size', 25], ['padding', 0], ['size', 25], ['padding', 5], ['size', 10], ['padding', 2.5], ['size', 99]];

        for (const [key, value] of moves) {
            state = controls.setLogoSetting(state, key, value);
            assert.equal(renderer.check(state).blocked, false, `${key} ${value}`);
        }
    });

    it('sets the shape and the two switches', () => {
        const withLogo = controls.addLogo(design());

        assert.equal(controls.setLogoSetting(withLogo, 'shape', 'circle').logo.shape, 'circle');
        assert.equal(controls.setLogoSetting(withLogo, 'backing', false).logo.backing, false);
        assert.equal(controls.setLogoSetting(withLogo, 'clearSpace', false).logo.clearSpace, false);
        assert.throws(() => controls.setLogoSetting(withLogo, 'shape', 'star'));
    });

    it('does nothing to a design that has no logo', () => {
        const start = design();

        assert.equal(controls.setLogoSetting(start, 'size', 20).logo, null);
    });
});

describe('the Look indicator', () => {
    it('names the Look while the settings match it and says Custom once one differs', () => {
        const rounded = renderer.applyLook(design(), 'bloom');

        assert.equal(controls.lookName(rounded), 'Bloom');
        assert.equal(controls.lookName(controls.setOption(rounded, 'dot', 'square')), 'Custom');
        assert.equal(controls.lookName(controls.setColour(rounded, 'bgColor', '#fffaf0')), 'Custom');
    });

    it('is not changed by the frame or the logo, which no Look sets', () => {
        const framed = controls.addLogo(controls.setOption(design(), 'frame', 'badge'));

        assert.equal(controls.lookName(framed), 'Rounded');
    });
});

describe('the scan-safety verdict for the page', () => {
    it('gives nothing to say about a safe design', () => {
        assert.deepEqual(controls.verdict(design()), { blocked: false, reasons: [], warnings: [] });
    });

    it('blocks with the reason stated when colours are too close', () => {
        const faint = controls.setColour(design(), 'codeColor', '#dddddd');
        const verdict = controls.verdict(faint);

        assert.equal(verdict.blocked, true);
        assert.match(verdict.reasons[0], /too close in colour/);
    });

    it('warns without blocking when contrast is between 3 and 4', () => {
        const middling = controls.setColour(design(), 'codeColor', '#8a8a8a');
        const verdict = controls.verdict(middling);

        assert.equal(verdict.blocked, false);
        assert.ok(verdict.warnings.length > 0);
    });

    it('warns about a light code on a dark background', () => {
        const inverted = controls.setColour(controls.setColour(design(), 'codeColor', '#ffffff'), 'bgColor', '#000000');
        const verdict = controls.verdict(inverted);

        assert.equal(verdict.blocked, false);
        assert.ok(verdict.warnings.some((warning) => /dark background/.test(warning)));
    });
});

describe('a redrawn design from the controls still renders', () => {
    it('draws after any control move', () => {
        const state = controls.setLogoSetting(controls.addLogo(controls.setOption(design(), 'frame', 'label')), 'shape', 'circle');

        assert.ok(drawn('https://example.com', state).svg.includes('<svg'));
    });
});

describe('the logo picture', () => {
    const file = (type, size = 1000) => ({ type, size });

    it('refuses SVG files', async () => {
        await assert.rejects(controls.readLogo(file('image/svg+xml')), /PNG, JPG or WebP/);
        assert.ok(!controls.LOGO_FILE_TYPES.includes('image/svg+xml'));
    });

    it('accepts PNG, JPEG and WebP only', () => {
        assert.deepEqual([...controls.LOGO_FILE_TYPES].sort(), ['image/jpeg', 'image/png', 'image/webp']);
    });

    it('refuses anything else, and a picture over the size limit', async () => {
        await assert.rejects(controls.readLogo(file('application/pdf')), /PNG, JPG or WebP/);
        await assert.rejects(controls.readLogo(file('image/png', options.logo.maxBytes + 1)), /5 MB/);
    });
});
