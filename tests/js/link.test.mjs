import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { runInThisContext } from 'node:vm';

runInThisContext(readFileSync(new URL('../../public/js/qr-link.js', import.meta.url), 'utf8'), { filename: 'qr-link.js' });

const { QrLink } = globalThis;
const fixture = JSON.parse(readFileSync(new URL('../fixtures/link-validation.json', import.meta.url), 'utf8'));

describe('the browser judges a link exactly as the server does', () => {
    for (const { url, valid, message } of fixture.cases) {
        it(`${JSON.stringify(url)} is ${valid ? 'accepted' : 'refused'}`, () => {
            const result = QrLink.validate(url, fixture.rules);

            assert.equal(result.valid, valid);

            if (!valid) {
                assert.equal(result.message, message);
            }
        });
    }
});

describe('what the field adds around the server rules', () => {
    it('asks for a link when the field is empty or only spaces', () => {
        for (const value of ['', '   ']) {
            const result = QrLink.check(value, fixture.rules);

            assert.equal(result.valid, false);
            assert.equal(result.empty, true);
        }
    });

    it('ignores spaces around a link, as the server trims them', () => {
        assert.equal(QrLink.check('  https://example.com  ', fixture.rules).valid, true);
        assert.equal(QrLink.check('  https://example.com  ', fixture.rules).link, 'https://example.com');
    });

    it('refuses a link too long to fit in a code', () => {
        const result = QrLink.check(`https://example.com/${'a'.repeat(2030)}`, fixture.rules);

        assert.equal(result.valid, false);
        assert.equal(result.message, 'That link is too long to fit in a QR code.');
    });

    it('accepts a link of exactly 2048 characters', () => {
        const link = `https://example.com/${'a'.repeat(2048 - 'https://example.com/'.length)}`;

        assert.equal(link.length, 2048);
        assert.equal(QrLink.check(link, fixture.rules).valid, true);
    });
});
