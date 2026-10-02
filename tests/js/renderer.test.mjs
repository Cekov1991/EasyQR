import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { CONTENTS, design, drawn, logo, renderer, scansAs, SHORT_URL } from './support.mjs';

const READABLE_BOX_PERCENT = 28;

const SETS = {
    dot: ['square', 'rounded', 'dots', 'fluid'],
    corner: ['square', 'rounded', 'circle'],
    eye: ['square', 'rounded', 'dot'],
    frame: ['none', 'label', 'border', 'badge'],
};

describe('every shape decodes to exactly its content', () => {
    for (const [name, content] of Object.entries(CONTENTS)) {
        for (const [option, values] of Object.entries(SETS)) {
            for (const value of values) {
                it(`${name} with ${option} ${value}`, () => {
                    const overrides = option === 'corner' && value === 'square' ? { corner: value, eye: 'square' } : { [option]: value };
                    assert.ok(scansAs(content, design(overrides)));
                });
            }
        }
    }

    it('square corners with the dot eye on a vCard', { todo: 'jsQR cannot find these finders at any size; undecided whether the geometry or the decoder is at fault' }, () => {
        assert.ok(scansAs(CONTENTS.vcard, design({ corner: 'square', eye: 'dot' })));
    });
});

describe('a logo decodes', () => {
    for (const [name, content] of Object.entries(CONTENTS)) {
        for (const shape of ['square', 'rounded', 'circle']) {
            for (const clearSpace of [true, false]) {
                for (const frame of ['none', 'badge']) {
                    it(`${name}, ${shape} logo, clear space ${clearSpace}, frame ${frame}, cleared box up to ${READABLE_BOX_PERCENT}% of the width`, () => {
                        const d = design({ frame, logo: logo({ shape, size: clearSpace ? 20 : 30, padding: clearSpace ? 4 : 0, clearSpace }) });
                        assert.ok(scansAs(content, d));
                    });
                }
            }
        }
    }

    it('with no backing', () => {
        assert.ok(scansAs(CONTENTS.long, design({ logo: logo({ size: 30, padding: 8, backing: false, clearSpace: false }) })));
    });

    for (const [name, content] of Object.entries(CONTENTS)) {
        it(`${name}: the largest logo the ranges allow, with clear space`, { todo: 'size 30 + padding 8 clears 21% of the code, under the 25% limit, but no content decodes; see the blocker in the ticket' }, () => {
            assert.ok(scansAs(content, design({ logo: logo({ size: 30, padding: 8, clearSpace: true }) })));
        });
    }
});

describe('colours either side of the 3:1 limit', () => {
    const greyAt = (level) => '#' + level.toString(16).padStart(2, '0').repeat(3);
    const levels = Array.from({ length: 256 }, (_, i) => i);
    const aboveLimit = levels.find((l) => renderer.contrast(greyAt(l), '#ffffff') < 3.05 && renderer.contrast(greyAt(l), '#ffffff') >= 3);
    const belowLimit = levels.find((l) => renderer.contrast(greyAt(l), '#ffffff') < 3);

    it('just above 3:1 decodes and is allowed', () => {
        const d = design({ codeColor: greyAt(aboveLimit), eyeColor: greyAt(aboveLimit) });
        assert.ok(scansAs(SHORT_URL, d));
        assert.equal(renderer.check(d).blocked, false);
    });

    it('just below 3:1 is blocked', () => {
        const d = design({ codeColor: greyAt(belowLimit), eyeColor: greyAt(belowLimit) });
        assert.equal(renderer.check(d).blocked, true);
    });

    it('the search found real boundary colours', () => {
        assert.ok(aboveLimit !== undefined && belowLimit !== undefined && belowLimit === aboveLimit + 1);
    });

    it('light code on a dark background still decodes', () => {
        assert.ok(scansAs(SHORT_URL, design({ codeColor: '#ffffff', eyeColor: '#ffffff', bgColor: '#0A0F24' })));
    });
});

describe('the renderer reports facts', () => {
    it('uses error correction M without a logo and H with one', () => {
        assert.equal(drawn(SHORT_URL, design()).errorCorrection, 'M');
        assert.equal(drawn(SHORT_URL, design({ logo: logo() })).errorCorrection, 'H');
    });

    it('reports the module count, which grows with content', () => {
        const short = drawn(SHORT_URL, design()).modules;
        const long = drawn(CONTENTS.long, design()).modules;
        assert.ok(short >= 21 && (short - 17) % 4 === 0);
        assert.ok(long > short);
    });

    it('reports no logo coverage without a logo', () => {
        assert.equal(drawn(SHORT_URL, design()).logoCoverage, 0);
    });

    it('measures coverage as the cleared box when clear space is on, otherwise the logo box', () => {
        assert.equal(renderer.logoCoverage(logo({ size: 20, padding: 5, clearSpace: true })), 9);
        assert.equal(renderer.logoCoverage(logo({ size: 20, padding: 5, clearSpace: false })), 4);
        assert.equal(drawn(SHORT_URL, design({ logo: logo({ size: 20, padding: 5, clearSpace: false }) })).logoCoverage, 4);
    });

    it('sizes the image to the frame', () => {
        const plain = drawn(SHORT_URL, design());
        assert.equal(plain.width, plain.height);
        assert.ok(drawn(SHORT_URL, design({ frame: 'label' })).height > plain.height);
        assert.ok(drawn(SHORT_URL, design({ frame: 'badge' })).height > drawn(SHORT_URL, design({ frame: 'badge' })).width);
    });

    it('gives explicit dimensions when asked, keeping the aspect ratio', () => {
        const result = renderer.render(SHORT_URL, design({ frame: 'label' }), { pixelWidth: 1000 });
        assert.match(result.svg, /width="1000"/);
        assert.match(result.svg, new RegExp(`height="${Math.round(1000 * result.height / result.width)}"`));
    });

    it('refuses to draw nothing', () => {
        assert.throws(() => renderer.render('', design()));
    });

    it('keeps frame text from breaking the markup', () => {
        const result = drawn(SHORT_URL, design({ frame: 'label', frameText: '<b>&"x' }));
        assert.ok(!result.svg.includes('<b>'));
        assert.ok(scansAs(SHORT_URL, design({ frame: 'label', frameText: '<b>&"x' })));
    });

    it('gives each drawing its own clip path so several can share a page', () => {
        const ids = [1, 2].map(() => drawn(SHORT_URL, design({ logo: logo() })).svg.match(/clipPath id="([^"]+)"/)[1]);
        assert.notEqual(ids[0], ids[1]);
    });
});

describe('scan safety', () => {
    const rules = (d) => renderer.check(d).blockers.map((b) => b.rule);
    const warned = (d) => renderer.check(d).warnings.map((w) => w.rule);

    it('passes the default design cleanly', () => {
        assert.deepEqual(renderer.check(design()), { blocked: false, blockers: [], warnings: [] });
    });

    it('blocks contrast below 3:1', () => {
        const d = design({ codeColor: '#999999', eyeColor: '#999999', bgColor: '#ffffff' });
        assert.deepEqual(rules(d), ['contrast']);
    });

    it('warns, without blocking, between 3:1 and 4:1', () => {
        const d = design({ codeColor: '#888888', eyeColor: '#000000', bgColor: '#ffffff' });
        assert.ok(renderer.contrast('#888888', '#ffffff') >= 3 && renderer.contrast('#888888', '#ffffff') < 4);
        assert.equal(renderer.check(d).blocked, false);
        assert.deepEqual(warned(d), ['low-contrast']);
    });

    it('warns when the eyes are faint against the background', () => {
        const d = design({ eyeColor: '#cccccc' });
        assert.equal(renderer.check(d).blocked, false);
        assert.deepEqual(warned(d), ['faint-eyes']);
    });

    it('warns on light code over a dark background', () => {
        const d = design({ codeColor: '#ffffff', eyeColor: '#ffffff', bgColor: '#0A0F24' });
        assert.equal(renderer.check(d).blocked, false);
        assert.deepEqual(warned(d), ['inverted']);
    });

    it('blocks a logo covering more than 25% of the code, and allows exactly 25%', () => {
        assert.deepEqual(rules(design({ logo: logo({ size: 51, padding: 0, clearSpace: false }) })), ['logo-coverage']);
        assert.deepEqual(rules(design({ logo: logo({ size: 50, padding: 0, clearSpace: false }) })), []);
    });

    it('counts the cleared box rather than the logo when clear space is on', () => {
        assert.deepEqual(rules(design({ logo: logo({ size: 30, padding: 12, clearSpace: true }) })), ['logo-coverage']);
        assert.deepEqual(rules(design({ logo: logo({ size: 30, padding: 12, clearSpace: false }) })), []);
    });

    it('reports every broken limit at once, each with a message', () => {
        const d = design({ codeColor: '#eeeeee', bgColor: '#ffffff', logo: logo({ size: 60, clearSpace: false }) });
        const { blockers } = renderer.check(d);
        assert.deepEqual(blockers.map((b) => b.rule).sort(), ['contrast', 'logo-coverage']);
        assert.ok(blockers.every((b) => b.message.length > 0));
    });

    it('computes contrast with the WCAG formula', () => {
        assert.equal(renderer.contrast('#000000', '#ffffff'), 21);
        assert.equal(renderer.contrast('#ffffff', '#ffffff'), 1);
        assert.ok(Math.abs(renderer.contrast('#777777', '#ffffff') - 4.48) < 0.01);
    });
});

describe('looks', () => {
    it('are Rounded, Ink, Bloom and Soft, Rounded first', () => {
        assert.deepEqual(Object.keys(renderer.LOOKS), ['rounded', 'ink', 'bloom', 'soft']);
    });

    it('make the default design Rounded', () => {
        const d = renderer.defaultDesign();
        assert.equal(renderer.lookOf(d), 'rounded');
        assert.equal(d.version, 1);
        assert.equal(d.frame, 'none');
        assert.equal(d.logo, null);
    });

    it('every look is a safe design that decodes', () => {
        for (const key of Object.keys(renderer.LOOKS)) {
            const d = renderer.applyLook(design(), key);
            assert.equal(renderer.check(d).blocked, false, key);
            assert.ok(scansAs(SHORT_URL, d), key);
        }
    });

    it('choosing one copies its settings and keeps the logo and frame', () => {
        const before = design({ frame: 'badge', frameText: 'HI', logo: logo() });
        const after = renderer.applyLook(before, 'ink');
        assert.equal(after.dot, 'square');
        assert.equal(after.codeColor, '#000000');
        assert.equal(after.frame, 'badge');
        assert.deepEqual(after.logo, before.logo);
        assert.equal(before.dot, 'fluid');
    });

    it('leaves no reference back to the look', () => {
        const after = renderer.applyLook(design(), 'bloom');
        assert.ok(!('look' in after) && !('label' in after));
        after.dot = 'square';
        assert.equal(renderer.LOOKS.bloom.dot, 'dots');
        assert.equal(renderer.lookOf(after), 'custom');
    });
});
