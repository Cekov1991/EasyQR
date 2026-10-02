/**
 * Draws a saved code from the content and Design a page hands it (ADR-0005), with
 * the one renderer. Read-only: this is how the codes table, the Top performing
 * widget and the View page show a code. A code with no usable Design is drawn in
 * the Rounded Look, because there is nothing to migrate.
 *
 * Needs `QrRenderer` (qr-renderer.js). Classic script, no build step; it also
 * loads in Node for `npm test`.
 */
(function (root) {
    'use strict';

    /** The Design to draw: the one given if it is a version 1 Design, otherwise Rounded. */
    function designOf(raw) {
        let design = raw;

        if (typeof raw === 'string') {
            try {
                design = JSON.parse(raw);
            } catch (e) {
                design = null;
            }
        }

        if (design && typeof design === 'object' && design.version === 1) {
            return design;
        }

        return root.QrRenderer.defaultDesign();
    }

    /**
     * The SVG of a code, or an empty string when it cannot be drawn (nothing to
     * encode, or more than a QR code holds), so one bad row never breaks a table.
     */
    function draw(content, rawDesign, options = {}) {
        try {
            return root.QrRenderer.render(content, designOf(rawDesign), options).svg;
        } catch (e) {
            return '';
        }
    }

    /**
     * Draws into an element carrying `data-qr-content` and `data-qr-design`, and
     * `data-qr-logo` when the code has a logo: the address of the logo route, so
     * the drawing never reaches another origin.
     */
    function paint(element) {
        element.innerHTML = draw(element.dataset.qrContent, element.dataset.qrDesign, { logoSrc: element.dataset.qrLogo || null });
    }

    root.QrDrawing = Object.freeze({ designOf, draw, paint });
})(globalThis);
