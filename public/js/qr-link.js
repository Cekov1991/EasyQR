/**
 * Decides whether a link may become a QR code, in the browser, with the same
 * rules and the same messages as App\Models\QrCode::validateUrl() (ADR-0005:
 * the link never leaves the page, so the page has to judge it). The prohibited
 * lists are rendered into the page from the model's constants and handed in as
 * `rules`; tests/fixtures/link-validation.json pins the two implementations
 * together from both sides.
 *
 * Classic script, no build step, no DOM: it also loads in Node for `npm test`.
 */
(function (root) {
    'use strict';

    const MAX_LENGTH = 2048;
    const AUTHORITY = /^(?:[a-z][a-z0-9+.-]*:)?\/\/([^/?#]*)([^?#]*)/i;

    function invalid(message) {
        return { valid: false, message };
    }

    /** The host and path of a link, or null when it has no host, as PHP's parse_url would see it. */
    function parse(url) {
        const match = AUTHORITY.exec(url);

        if (!match) {
            return null;
        }

        const withoutUser = match[1].slice(match[1].lastIndexOf('@') + 1);
        const host = withoutUser.startsWith('[')
            ? withoutUser.slice(0, withoutUser.indexOf(']') + 1)
            : withoutUser.replace(/:\d*$/, '');

        return host === '' ? null : { host: host.toLowerCase(), path: match[2] };
    }

    /**
     * @param {string} url the link, as typed
     * @param {{domains: string[], keywords: string[], extensions: string[]}} rules
     * @returns {{valid: boolean, message: string}}
     */
    function validate(url, rules) {
        if (!url || url === '0') {
            return invalid('Please enter a valid URL.');
        }

        const parsed = parse(url);

        if (!parsed) {
            return invalid('Invalid URL format. Please enter a complete URL (e.g., https://example.com)');
        }

        if (!url.startsWith('https://') && !url.startsWith('http://localhost') && !url.startsWith('http://127.0.0.1')) {
            return invalid('For security reasons, only HTTPS URLs are allowed. Please use https:// instead of http://');
        }

        for (const domain of rules.domains) {
            if (parsed.host.includes(domain.toLowerCase())) {
                return invalid(`URL shortening services like '${domain}' are not allowed. Please use the direct URL to your content.`);
            }
        }

        const lowered = url.toLowerCase();

        for (const keyword of rules.keywords) {
            if (lowered.includes(keyword)) {
                return invalid(`URLs containing '${keyword}' are not permitted for security reasons. Please use a different URL.`);
            }
        }

        const path = parsed.path.toLowerCase();

        for (const extension of rules.extensions) {
            if (path.endsWith(extension.toLowerCase())) {
                return invalid(`Executable files (${extension}) are not allowed for security reasons. Please link to a webpage instead.`);
            }
        }

        if (/^https?:\/\/\d+\.\d+\.\d+\.\d+/.test(url) && !url.startsWith('http://127.0.0.1') && !url.startsWith('http://localhost')) {
            return invalid('Direct IP addresses are not allowed for security reasons. Please use a proper domain name.');
        }

        return { valid: true, message: 'URL is valid' };
    }

    /**
     * What the link field does with what was typed: trims it, asks for one when
     * it is empty, and refuses one too long to fit in a code, before the shared
     * rules run.
     *
     * @returns {{valid: boolean, message: string, empty?: boolean, link?: string}}
     */
    function check(value, rules) {
        const link = String(value ?? '').trim();

        if (link === '') {
            return { valid: false, empty: true, message: 'Please enter a URL.' };
        }

        if (link.length > MAX_LENGTH) {
            return invalid('That link is too long to fit in a QR code.');
        }

        const result = validate(link, rules);

        return result.valid ? { ...result, link } : result;
    }

    root.QrLink = Object.freeze({ validate, check, MAX_LENGTH });
})(globalThis);
