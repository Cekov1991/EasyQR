/**
 * The dashboard's Design editor, "Studio" (ADR-0005). It binds the shared Design
 * controls (qr-design-controls.js) to the Filament form: the form's state is
 * the Design, the code is drawn by the one renderer, and what the code encodes is
 * asked of the page, on the server, a moment after the other fields change.
 *
 * `mount(element, wire)` takes the editor's root element and the Livewire `$wire`
 * of the page. Needs `QrRenderer` and `QrDesignControls`. Classic script, no build step.
 */
(function (root) {
    'use strict';

    const renderer = root.QrRenderer;
    const controls = root.QrDesignControls;

    /** The form fields a code's encoded content depends on. */
    const CONTENT_FIELDS = Object.freeze(['type', 'qr_content_type', 'qr_content_data', 'short_url']);

    /** How long the form must be still before the page is asked what the code encodes. */
    const CONTENT_DELAY_MS = 400;

    /** What a Look's thumbnail shows until the code has content of its own. */
    const SAMPLE_CONTENT = 'EasyQR';

    const TOO_LONG = 'That is more than fits in a QR code. Shorten it.';
    const UPLOADING = 'Uploading your logo...';
    const UPLOAD_FAILED = 'That logo could not be uploaded. Try another.';

    const clone = (value) => JSON.parse(JSON.stringify(value));

    /** The Design to start from: the form's, or Rounded when the form holds nothing usable. */
    function startingDesign(state) {
        return state && typeof state === 'object' && state.version === 1 ? clone(state) : renderer.defaultDesign();
    }

    /** The notes shown beside the preview, blocked reasons first. */
    function notices(design) {
        const verdict = controls.verdict(design);

        return [
            ...verdict.reasons.map((text) => ({ tone: 'blocked', text: `${text} Saving is off until this is fixed.` })),
            ...verdict.warnings.map((text) => ({ tone: 'warning', text })),
        ];
    }

    function mount(element, wire) {
        const path = element.dataset.statePath;
        const one = (selector) => element.querySelector(selector);
        const code = one('[data-studio-code]');
        const empty = one('[data-studio-empty]');
        const list = one('[data-studio-notices]');
        const looks = one('[data-studio-looks]');
        const lookName = one('[data-studio-look-name]');

        let design = startingDesign(wire.$get(path));
        let logoSrc = design.logo ? element.dataset.logoUrl || null : null;
        let uploads = 0;
        let content = element.dataset.encodedContent || '';
        let timer = null;
        let asked = 0;
        let inputs = JSON.stringify(CONTENT_FIELDS.map((field) => wire.$get(`data.${field}`) ?? null));

        const lookButtons = {};

        Object.keys(renderer.LOOKS).forEach((key) => {
            const button = document.createElement('button');
            const thumb = document.createElement('span');
            const name = document.createElement('span');

            button.type = 'button';
            button.className = 'eq-look';
            button.dataset.look = key;
            thumb.className = 'eq-look-thumb';
            thumb.setAttribute('aria-hidden', 'true');
            name.className = 'eq-look-name';
            name.textContent = renderer.LOOKS[key].label;
            button.append(thumb, name);
            button.addEventListener('click', () => {
                design = renderer.applyLook(design, key);
                panel.update({ design });
                save();
                draw();
            });
            looks.append(button);
            lookButtons[key] = button;
        });

        function save() {
            wire.$set(path, clone(design), false);
        }

        function drawLooks() {
            const active = renderer.lookOf(design);
            const sample = content || SAMPLE_CONTENT;

            Object.keys(lookButtons).forEach((key) => {
                const button = lookButtons[key];

                button.setAttribute('aria-pressed', String(key === active));
                button.querySelector('.eq-look-thumb').innerHTML = renderer.render(
                    sample,
                    renderer.applyLook({ ...design, frame: 'none', logo: null }, key),
                ).svg;
            });

            lookName.textContent = controls.lookName(design);
        }

        function drawNotices(extra = []) {
            list.replaceChildren(...[...extra, ...notices(design)].map(({ tone, text }) => {
                const item = document.createElement('li');

                item.dataset.tone = tone;
                item.textContent = text;

                return item;
            }));
        }

        function draw() {
            let drawn = null;
            let problem = [];

            if (content) {
                try {
                    drawn = renderer.render(content, design, { logoSrc: design.logo ? logoSrc : null });
                } catch (e) {
                    problem = [{ tone: 'blocked', text: TOO_LONG }];
                }
            }

            code.innerHTML = drawn ? drawn.svg : '';
            code.style.aspectRatio = drawn ? `${drawn.width} / ${drawn.height}` : '';
            code.hidden = !drawn;
            empty.hidden = Boolean(drawn) || problem.length > 0;
            drawLooks();
            drawNotices(problem);
        }

        async function refreshContent() {
            const ticket = ++asked;
            const next = await wire.encodedContent();

            if (ticket === asked && typeof next === 'string' && next !== content) {
                content = next;
                draw();
            }
        }

        function contentFieldChanged() {
            const now = JSON.stringify(CONTENT_FIELDS.map((field) => wire.$get(`data.${field}`) ?? null));

            if (now === inputs) {
                return;
            }

            inputs = now;
            window.clearTimeout(timer);
            timer = window.setTimeout(refreshContent, CONTENT_DELAY_MS);
        }

        CONTENT_FIELDS.forEach((field) => wire.$watch(`data.${field}`, contentFieldChanged));

        function say(text) {
            element.querySelectorAll('[data-logo-message]').forEach((message) => {
                message.textContent = text;
            });
        }

        function dropLogo(reason) {
            design = { ...design, logo: null };
            logoSrc = null;
            panel.update({ design, logoSrc });
            save();
            draw();
            say(reason);
        }

        /**
         * Puts the picked file on the bucket through the page, and writes the path it
         * was stored under into the Design. The picture in the preview is the local
         * one meanwhile, so nothing waits on the upload to be drawn.
         */
        function upload(file) {
            const ticket = ++uploads;

            say(UPLOADING);
            wire.upload('designLogoUpload', file, async () => {
                const answer = await wire.storeDesignLogo();

                if (ticket !== uploads) {
                    return;
                }

                if (answer && answer.path) {
                    design = { ...design, logo: { ...design.logo, path: answer.path } };
                    panel.update({ design });
                    save();
                    say('');
                } else {
                    dropLogo((answer && answer.error) || UPLOAD_FAILED);
                }
            }, () => {
                if (ticket === uploads) {
                    dropLogo(UPLOAD_FAILED);
                }
            });
        }

        const panel = controls.mount(element, {
            design,
            logoSrc,
            onChange: (state) => {
                design = state.design;
                logoSrc = state.logoSrc;

                if (!design.logo) {
                    uploads++;
                }

                save();
                draw();
            },
            onLogoFile: upload,
        });

        draw();

        return { design: () => clone(design), content: () => content };
    }

    root.QrDesignEditor = Object.freeze({ mount, notices, startingDesign });
})(globalThis);
