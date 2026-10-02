/**
 * The Design controls, shared by every editor (ADR-0005): the homepage wizard
 * today, the dashboard's Design field later. The markup comes from the
 * `x-qr-design-controls.*` Blade components and carries only data attributes;
 * this file binds to them, so the controls work wherever those components are
 * placed. The pure functions at the top make every control move and are what
 * `npm test` exercises; `mount` is the thin DOM layer over them.
 *
 * Needs the global `QrRenderer`. Classic script, no build step.
 */
(function (root) {
    'use strict';

    const renderer = root.QrRenderer;

    const SETS = Object.freeze(Object.fromEntries(
        Object.entries(renderer.OPTIONS).map(([setting, { values }]) => [setting, Object.freeze(Object.keys(values))]),
    ));

    const COLOURS = Object.freeze(Object.keys(renderer.SWATCHES));
    const MAX_FRAME_TEXT = renderer.MAX_FRAME_TEXT;
    const LOGO_FILE_TYPES = Object.freeze([...root.QrDesignOptions.logo.fileTypes]);
    const LOGO_MAX_BYTES = root.QrDesignOptions.logo.maxBytes;
    const LOGO_MAX_PIXELS = root.QrDesignOptions.logo.maxPixels;
    const DEFAULT_LOGO = renderer.DEFAULT_LOGO;

    function normaliseHex(value) {
        const text = String(value ?? '').trim().replace(/^#/, '').toLowerCase();

        if (/^[0-9a-f]{3}$/.test(text)) {
            return '#' + text.split('').map((c) => c + c).join('');
        }

        return /^[0-9a-f]{6}$/.test(text) ? '#' + text : null;
    }

    function setOption(design, setting, value) {
        if (!SETS[setting] || setting === 'logoShape' || !SETS[setting].includes(value)) {
            throw new RangeError(`${value} is not a ${setting}`);
        }

        return { ...design, [setting]: value };
    }

    function setColour(design, setting, value) {
        if (!COLOURS.includes(setting)) {
            throw new RangeError(`${setting} is not a colour`);
        }

        const hex = normaliseHex(value);

        return hex ? { ...design, [setting]: hex } : { ...design };
    }

    function setFrameText(design, text) {
        return { ...design, frameText: String(text ?? '').slice(0, MAX_FRAME_TEXT) };
    }

    function addLogo(design) {
        return { ...design, logo: design.logo ? { ...design.logo } : { ...DEFAULT_LOGO } };
    }

    function removeLogo(design) {
        return { ...design, logo: null };
    }

    /** Changes one logo setting; size and padding are clamped so the hidden box can never pass the limit. */
    function setLogoSetting(design, setting, value) {
        if (!design.logo) {
            return { ...design };
        }

        if (setting === 'shape') {
            if (!SETS.logoShape.includes(value)) {
                throw new RangeError(`${value} is not a logo shape`);
            }

            return { ...design, logo: { ...design.logo, shape: value } };
        }

        if (setting === 'backing' || setting === 'clearSpace') {
            return { ...design, logo: { ...design.logo, [setting]: Boolean(value) } };
        }

        if (setting === 'size' || setting === 'padding') {
            return { ...design, logo: renderer.clampLogo({ ...design.logo, [setting]: Number(value) }) };
        }

        throw new RangeError(`${setting} is not a logo setting`);
    }

    /** The ranges the logo sliders may offer right now; the size maximum follows the padding. */
    function logoLimits(design) {
        const limits = renderer.LOGO_LIMITS;
        const padding = design.logo ? design.logo.padding : 0;

        return {
            size: { min: limits.size.min, max: renderer.maxLogoSize(padding) },
            padding: { min: limits.padding.min, max: limits.padding.max },
        };
    }

    /** The Look the settings match, or "Custom" once one differs from every Look. */
    function lookName(design) {
        const key = renderer.lookOf(design);

        return key === 'custom' ? 'Custom' : renderer.LOOKS[key].label;
    }

    /** What the page says about scan safety: the reasons a download is blocked, and the warnings beside the preview. */
    function verdict(design) {
        const checked = renderer.check(design);

        return {
            blocked: checked.blocked,
            reasons: checked.blockers.map((item) => item.message),
            warnings: checked.warnings.map((item) => item.message),
        };
    }

    /* ------------------------------------------------------------ the DOM -- */

    const rounded = renderer.roundedRect;

    /** A small picture for an option button, drawn with the same primitives as the code. */
    function icon(setting, value) {
        const wrap = (inner) => `<svg viewBox="0 0 40 40" width="22" height="22" aria-hidden="true" focusable="false">${inner}</svg>`;
        const grid = '<rect x="12" y="10" width="7" height="7" fill="currentColor"/><rect x="21" y="10" width="7" height="7" fill="currentColor"/><rect x="12" y="19" width="7" height="7" fill="currentColor"/><rect x="21" y="19" width="7" height="7" fill="currentColor"/>';

        if (setting === 'dot') {
            if (value === 'fluid') {
                return wrap(`<path d="${rounded(4, 4, 32, 15, 7.5)} ${rounded(4, 21, 15, 15, 7.5)} ${rounded(21, 21, 15, 15, 7.5, 0, 7.5, 7.5)}" fill="currentColor"/>`);
            }

            return wrap([[0, 0], [0, 1], [1, 0], [1, 1]].map(([r, c]) => {
                const x = 4 + c * 17;
                const y = 4 + r * 17;

                return value === 'dots'
                    ? `<circle cx="${x + 7.5}" cy="${y + 7.5}" r="7" fill="currentColor"/>`
                    : `<rect x="${x}" y="${y}" width="15" height="15" rx="${value === 'rounded' ? 4 : 0}" fill="currentColor"/>`;
            }).join(''));
        }

        if (setting === 'corner') {
            return value === 'circle'
                ? wrap('<circle cx="20" cy="20" r="14" fill="none" stroke="currentColor" stroke-width="5"/>')
                : wrap(`<rect x="6" y="6" width="28" height="28" rx="${value === 'rounded' ? 8 : 0}" fill="none" stroke="currentColor" stroke-width="5"/>`);
        }

        if (setting === 'eye' || setting === 'logoShape') {
            return value === 'dot' || value === 'circle'
                ? wrap('<circle cx="20" cy="20" r="9" fill="currentColor"/>')
                : wrap(`<rect x="11" y="11" width="18" height="18" rx="${value === 'rounded' ? 5 : 0}" fill="currentColor"/>`);
        }

        if (setting === 'frame') {
            if (value === 'none') {
                return wrap(`<g opacity=".45">${grid}</g>`);
            }
            if (value === 'label') {
                return wrap(`${grid}<rect x="10" y="30" width="20" height="4" rx="2" fill="currentColor"/>`);
            }
            if (value === 'border') {
                return wrap(`<rect x="5" y="4" width="30" height="30" rx="7" fill="none" stroke="currentColor" stroke-width="3"/>${grid}`);
            }

            return wrap(`<rect x="4" y="3" width="32" height="34" rx="7" fill="currentColor"/><rect x="9" y="7" width="22" height="21" rx="3" fill="#fff"/><g transform="translate(0 -1)">${grid}</g>`);
        }

        return '';
    }

    function readAsDataUrl(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = () => resolve(String(reader.result));
            reader.onerror = () => reject(new Error('unreadable'));
            reader.readAsDataURL(file);
        });
    }

    /** Scales a picture down so a big photo does not make every redraw heavy. */
    function shrink(dataUrl) {
        return new Promise((resolve) => {
            const image = new Image();

            image.onload = () => {
                const scale = Math.min(1, LOGO_MAX_PIXELS / Math.max(image.naturalWidth, image.naturalHeight));

                if (scale === 1) {
                    resolve(dataUrl);

                    return;
                }

                const canvas = document.createElement('canvas');

                canvas.width = Math.round(image.naturalWidth * scale);
                canvas.height = Math.round(image.naturalHeight * scale);
                canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                resolve(canvas.toDataURL('image/png'));
            };
            image.onerror = () => resolve(dataUrl);
            image.src = dataUrl;
        });
    }

    /**
     * Reads a logo from a local file with FileReader. It never leaves the browser.
     *
     * @returns {Promise<string>} a data URL, or rejects with a message fit to show
     */
    async function readLogo(file) {
        if (!LOGO_FILE_TYPES.includes(file.type)) {
            throw new Error('Choose a PNG, JPG or WebP picture.');
        }

        if (file.size > LOGO_MAX_BYTES) {
            throw new Error('That picture is over 5 MB. Choose a smaller one.');
        }

        let dataUrl;

        try {
            dataUrl = await readAsDataUrl(file);
        } catch (e) {
            throw new Error('That file could not be read. Try another.');
        }

        return shrink(dataUrl);
    }

    /**
     * Binds the controls found inside `container`. State is the Design plus the logo
     * picture; the picture is kept beside the Design because a Design never carries
     * pixels. Every change calls `onChange({ design, logoSrc })`.
     *
     * @returns {{update: Function, state: Function}}
     */
    function mount(container, options) {
        let state = { design: options.design, logoSrc: options.logoSrc ?? null };

        const all = (selector) => Array.from(container.querySelectorAll(selector));
        const one = (selector) => container.querySelector(selector);

        all('[data-setting]').forEach((button) => {
            const holder = button.querySelector('[data-icon]');

            if (holder && !holder.innerHTML) {
                holder.innerHTML = icon(button.dataset.setting, button.dataset.value);
            }
        });

        function emit(design, logoSrc = state.logoSrc) {
            state = { design, logoSrc };
            paint();
            options.onChange({ design: state.design, logoSrc: state.logoSrc });
        }

        function paint() {
            const { design, logoSrc } = state;

            all('[data-setting]').forEach((button) => {
                const { setting, value } = button.dataset;
                const current = setting === 'logoShape' ? design.logo?.shape : design[setting];

                button.setAttribute('aria-pressed', String(current === value));
            });

            COLOURS.forEach((setting) => {
                all(`[data-colour-swatch="${setting}"]`).forEach((swatch) => {
                    swatch.setAttribute('aria-pressed', String(swatch.dataset.value.toLowerCase() === design[setting].toLowerCase()));
                });
                all(`[data-colour-picker="${setting}"]`).forEach((picker) => {
                    picker.value = design[setting];
                });
                all(`[data-colour-hex="${setting}"]`).forEach((field) => {
                    if (document.activeElement !== field) {
                        field.value = design[setting];
                        field.removeAttribute('aria-invalid');
                    }
                });
            });

            all('[data-frame-text-field]').forEach((field) => {
                field.hidden = design.frame !== 'label' && design.frame !== 'badge';
            });
            all('[data-frame-text]').forEach((field) => {
                if (document.activeElement !== field) {
                    field.value = design.frameText;
                }
            });

            all('[data-logo-settings]').forEach((panel) => {
                panel.hidden = !design.logo;
            });
            all('[data-logo-remove]').forEach((button) => {
                button.hidden = !design.logo;
            });
            all('[data-logo-pick-label]').forEach((label) => {
                label.textContent = design.logo ? 'Replace logo' : 'Add logo';
            });
            all('[data-logo-thumb]').forEach((thumb) => {
                thumb.innerHTML = '';

                if (logoSrc && design.logo) {
                    const image = document.createElement('img');

                    image.src = logoSrc;
                    image.alt = 'Your logo';
                    thumb.append(image);
                }
            });

            if (design.logo) {
                const limits = logoLimits(design);

                ['size', 'padding'].forEach((setting) => {
                    all(`[data-logo-range="${setting}"]`).forEach((range) => {
                        range.min = limits[setting].min;
                        range.max = limits[setting].max;
                        range.value = design.logo[setting];
                        range.setAttribute('aria-valuetext', `${design.logo[setting]} percent`);
                    });
                    all(`[data-logo-value="${setting}"]`).forEach((out) => {
                        out.textContent = `${design.logo[setting]}%`;
                    });
                });
                ['backing', 'clearSpace'].forEach((setting) => {
                    all(`[data-logo-switch="${setting}"]`).forEach((box) => {
                        box.checked = design.logo[setting];
                    });
                });
            }
        }

        function say(text) {
            all('[data-logo-message]').forEach((message) => {
                message.textContent = text;
            });
        }

        container.addEventListener('click', (event) => {
            const button = event.target.closest('button');

            if (!button || !container.contains(button)) {
                return;
            }

            if (button.dataset.setting) {
                const { setting, value } = button.dataset;

                emit(setting === 'logoShape' ? setLogoSetting(state.design, 'shape', value) : setOption(state.design, setting, value));
            } else if (button.dataset.colourSwatch) {
                emit(setColour(state.design, button.dataset.colourSwatch, button.dataset.value));
            } else if (button.hasAttribute('data-logo-pick')) {
                one('[data-logo-file]').click();
            } else if (button.hasAttribute('data-logo-remove')) {
                say('');
                emit(removeLogo(state.design), null);
            }
        });

        container.addEventListener('input', (event) => {
            const field = event.target;

            if (field.dataset.colourPicker) {
                emit(setColour(state.design, field.dataset.colourPicker, field.value));
            } else if (field.dataset.colourHex) {
                const hex = normaliseHex(field.value);

                field.toggleAttribute('aria-invalid', hex === null);

                if (hex) {
                    emit(setColour(state.design, field.dataset.colourHex, hex));
                }
            } else if (field.hasAttribute('data-frame-text')) {
                emit(setFrameText(state.design, field.value));
            } else if (field.dataset.logoRange) {
                emit(setLogoSetting(state.design, field.dataset.logoRange, field.value));
            }
        });

        container.addEventListener('change', async (event) => {
            const field = event.target;

            if (field.dataset.logoSwitch) {
                emit(setLogoSetting(state.design, field.dataset.logoSwitch, field.checked));
            } else if (field.hasAttribute('data-logo-file') && field.files && field.files[0]) {
                try {
                    const logoSrc = await readLogo(field.files[0]);

                    say('');
                    emit(addLogo(state.design), logoSrc);
                } catch (e) {
                    say(e.message);
                }

                field.value = '';
            } else if (field.dataset.colourHex) {
                field.value = state.design[field.dataset.colourHex];
                field.removeAttribute('aria-invalid');
            }
        });

        paint();

        return {
            update(next) {
                state = { design: next.design, logoSrc: next.logoSrc ?? state.logoSrc };
                paint();
            },
            state: () => state,
        };
    }

    root.QrDesignControls = Object.freeze({
        SETS, COLOURS, MAX_FRAME_TEXT, LOGO_FILE_TYPES, normaliseHex, setOption, setColour, setFrameText, addLogo, removeLogo,
        setLogoSetting, logoLimits, lookName, verdict, readLogo, icon, mount,
    });
})(globalThis);
