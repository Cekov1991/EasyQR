/**
 * Downloads for a saved code, all built in the browser (ADR-0005): a PNG at a
 * chosen width, the SVG, and a ZIP of every format. The ZIP is written here, as
 * a stored (uncompressed) archive, so no library is needed for it.
 *
 * Needs `QrRenderer` and `QrDrawing`. Classic script, no build step; the parts
 * that need no DOM also load in Node for `npm test`.
 */
(function (root) {
    'use strict';

    const SVG_PIXELS = 1024;
    const encoder = new TextEncoder();

    /* ------------------------------------------------------------- the ZIP -- */

    const CRC_TABLE = (() => {
        const table = new Uint32Array(256);

        for (let n = 0; n < 256; n++) {
            let c = n;

            for (let k = 0; k < 8; k++) {
                c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
            }

            table[n] = c >>> 0;
        }

        return table;
    })();

    function crc32(bytes) {
        let crc = 0xFFFFFFFF;

        for (let i = 0; i < bytes.length; i++) {
            crc = CRC_TABLE[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
        }

        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    /**
     * A ZIP archive holding the entries as they are.
     *
     * @param {{name: string, data: Uint8Array}[]} entries
     * @returns {Uint8Array}
     */
    function zip(entries) {
        const parts = [];
        const directory = [];
        let offset = 0;

        const header = (size, write) => {
            const view = new DataView(new ArrayBuffer(size));

            write(view);

            return new Uint8Array(view.buffer);
        };

        entries.forEach((entry) => {
            const name = encoder.encode(entry.name);
            const crc = crc32(entry.data);
            const common = (view, at) => {
                view.setUint16(at, 20, true);
                view.setUint16(at + 2, 0x0800, true);
                view.setUint16(at + 4, 0, true);
                view.setUint16(at + 6, 0, true);
                view.setUint16(at + 8, 0x21, true);
                view.setUint32(at + 10, crc, true);
                view.setUint32(at + 14, entry.data.length, true);
                view.setUint32(at + 18, entry.data.length, true);
                view.setUint16(at + 22, name.length, true);
                view.setUint16(at + 24, 0, true);
            };

            const local = header(30, (view) => {
                view.setUint32(0, 0x04034b50, true);
                common(view, 4);
            });

            directory.push({ name, offset, crc, size: entry.data.length, common });
            parts.push(local, name, entry.data);
            offset += local.length + name.length + entry.data.length;
        });

        const start = offset;

        directory.forEach((item) => {
            const central = header(46, (view) => {
                view.setUint32(0, 0x02014b50, true);
                view.setUint16(4, 20, true);
                item.common(view, 6);
                view.setUint16(32, 0, true);
                view.setUint16(34, 0, true);
                view.setUint16(36, 0, true);
                view.setUint32(38, 0, true);
                view.setUint32(42, item.offset, true);
            });

            parts.push(central, item.name);
            offset += central.length + item.name.length;
        });

        parts.push(header(22, (view) => {
            view.setUint32(0, 0x06054b50, true);
            view.setUint16(8, directory.length, true);
            view.setUint16(10, directory.length, true);
            view.setUint32(12, offset - start, true);
            view.setUint32(16, start, true);
        }));

        const archive = new Uint8Array(parts.reduce((total, part) => total + part.length, 0));
        let at = 0;

        parts.forEach((part) => {
            archive.set(part, at);
            at += part.length;
        });

        return archive;
    }

    /* ------------------------------------------------------- the formats -- */

    /** The SVG file for a code, with explicit dimensions. */
    function svgText(content, design, options = {}) {
        return root.QrRenderer.render(content, root.QrDrawing.designOf(design), { ...options, pixelWidth: SVG_PIXELS }).svg;
    }

    /**
     * Every format of a code: the SVG and a PNG at each size. `rasterise` turns SVG
     * text into PNG bytes at a width; the browser's is `rasterise` below, and the
     * tests hand in their own.
     *
     * @param {(svg: string, width: number, height: number) => Promise<Uint8Array>} rasterise
     * @returns {Promise<{name: string, data: Uint8Array}[]>}
     */
    async function bundleEntries(content, design, fileName, sizes, rasterise, options = {}) {
        const entries = [{ name: `${fileName}.svg`, data: encoder.encode(svgText(content, design, options)) }];

        for (const width of sizes) {
            const drawn = root.QrRenderer.render(content, root.QrDrawing.designOf(design), { ...options, pixelWidth: width });
            const height = Math.round(width * drawn.height / drawn.width);

            entries.push({ name: `${fileName}-${width}px.png`, data: await rasterise(drawn.svg, width, height) });
        }

        return entries;
    }

    /* -------------------------------------------------------------- logo -- */

    /**
     * The logo as a data URL. A file saved to disk, and a picture drawn from an SVG
     * blob, cannot reach back to the site for an image, so the logo travels inside
     * them. The address is the logo route, on this origin, which is what keeps the
     * canvas untainted. Nothing to inline is null; a logo that cannot be fetched
     * rejects, so a download never quietly leaves the logo out.
     *
     * @param {string|null|undefined} url
     * @param {typeof fetch} [fetcher]
     * @returns {Promise<string|null>}
     */
    async function inlineLogo(url, fetcher = root.fetch) {
        if (!url) {
            return null;
        }

        const response = await fetcher(url, { credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error('logo unavailable');
        }

        const blob = await response.blob();
        const bytes = new Uint8Array(await blob.arrayBuffer());
        let binary = '';

        for (let i = 0; i < bytes.length; i += 0x8000) {
            binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
        }

        return `data:${blob.type || 'image/png'};base64,${root.btoa(binary)}`;
    }

    /* ----------------------------------------------------------- browser -- */

    function rasteriseBlob(svg, width, height) {
        return new Promise((resolve, reject) => {
            const source = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
            const image = new Image();

            image.onload = () => {
                const canvas = document.createElement('canvas');

                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(image, 0, 0, width, height);
                URL.revokeObjectURL(source);
                canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('empty'))), 'image/png');
            };

            image.onerror = () => {
                URL.revokeObjectURL(source);
                reject(new Error('unreadable'));
            };

            image.src = source;
        });
    }

    async function rasterise(svg, width, height) {
        return new Uint8Array(await (await rasteriseBlob(svg, width, height)).arrayBuffer());
    }

    function save(blob, name) {
        const anchor = document.createElement('a');

        anchor.href = URL.createObjectURL(blob);
        anchor.download = name;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        setTimeout(() => URL.revokeObjectURL(anchor.href), 1000);
    }

    /**
     * Wires the buttons inside `container`: `[data-qr-download="png|svg|zip"]`, the
     * width in `[data-qr-size]`, the code in `[data-qr-source]` and a message line
     * in `[data-qr-status]`. The file name comes from `data-qr-file-name`.
     */
    function mount(container) {
        const source = container.querySelector('[data-qr-source]');
        const sizeSelect = container.querySelector('[data-qr-size]');
        const status = container.querySelector('[data-qr-status]');
        const fileName = container.dataset.qrFileName || 'qr-code';
        const sizes = Array.from(sizeSelect.options).map((option) => parseInt(option.value, 10));
        const say = (text) => { status.textContent = text; };
        const content = () => source.dataset.qrContent;
        const design = () => source.dataset.qrDesign;
        const logoSrc = () => inlineLogo(source.dataset.qrLogo);

        const actions = {
            async png() {
                const width = parseInt(sizeSelect.value, 10);
                const drawn = root.QrRenderer.render(content(), root.QrDrawing.designOf(design()), { pixelWidth: width, logoSrc: await logoSrc() });

                save(await rasteriseBlob(drawn.svg, width, Math.round(width * drawn.height / drawn.width)), `${fileName}-${width}px.png`);
            },
            async svg() {
                save(new Blob([svgText(content(), design(), { logoSrc: await logoSrc() })], { type: 'image/svg+xml' }), `${fileName}.svg`);
            },
            async zip() {
                const entries = await bundleEntries(content(), design(), fileName, sizes, rasterise, { logoSrc: await logoSrc() });

                save(new Blob([zip(entries)], { type: 'application/zip' }), `${fileName}-qr-codes.zip`);
            },
        };

        container.querySelectorAll('[data-qr-download]').forEach((button) => {
            button.addEventListener('click', async () => {
                say('');

                try {
                    await actions[button.dataset.qrDownload]();
                    say('Downloaded.');
                } catch (e) {
                    say('Your browser could not make that file. Try another format.');
                }
            });
        });
    }

    root.QrDownload = Object.freeze({ zip, crc32, svgText, bundleEntries, inlineLogo, rasterise, mount });
})(globalThis);
