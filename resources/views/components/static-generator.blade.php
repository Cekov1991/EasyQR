@props(['page' => 'home'])

{{--
    The static generator, as the Steps editor: the link field in the hero, the
    visitor's own code drawn beneath it as they type, and a short wizard —
    Look, Logo, then Download. Shared by the homepage and the Landing Pages, so the
    code a visitor downloads is the same thing wherever they made it.

    The code is drawn in the browser by the one renderer (public/js/qr-renderer.js,
    ADR-0005). The link never leaves the page, and neither does a logo: it is read
    from a local file with FileReader and drawn into the code here. The only
    thing sent to us is an event, and none of them carries the link.

    The Design controls are the <x-qr-design-controls.*> components, bound by
    public/js/qr-design-controls.js, so the dashboard editor can use the same ones. The offer still opens after the
    first download.

    `page` names the page it is embedded on: `home`, or a Landing Page slug.
    The offer and the inline link carry it to registration as the Signup
    Landing Page, beside their own refs, and every event carries it too.
    Anything passed in the slot sits below the editor in a grid of its own,
    which is how the homepage keeps its dynamic teaser card.

    One per page: the element ids are fixed, and the script is pushed once.
--}}
@pushOnce('styles')
    <link href="{{ \App\Support\Asset::versioned('css/qr-design-controls.css') }}" rel="stylesheet">
@endPushOnce

<div class="eq-editor-wrap">

    {{-- The hero's link field. Everything below it appears once the link is valid. --}}
    <form id="static-qr-form" class="eq-editor-link" data-page="{{ $page }}" novalidate>
        <label for="static-url" class="eq-label">Website or link</label>
        <input type="text" id="static-url" class="eq-input eq-input--hero" placeholder="https://example.com" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="url" aria-describedby="static-error">
        <p id="static-error" class="eq-editor-message" data-tone="hint" role="status">Your QR code appears here as soon as you type a link.</p>
        <p class="eq-editor-promise">Free, no account. We never store your code or its link.</p>
    </form>

    {{-- The editor: lives outside the cards so drawing a code never changes their height. --}}
    <div id="static-result" class="eq-editor" hidden>

        <ol class="eq-steps">
            <li><button type="button" class="eq-step" data-step="look" aria-current="step"><span class="eq-step-number" aria-hidden="true">1</span>Look</button></li>
            <li><button type="button" class="eq-step" data-step="logo" aria-current="false"><span class="eq-step-number" aria-hidden="true">2</span>Logo</button></li>
            <li><button type="button" class="eq-step" data-step="download" aria-current="false"><span class="eq-step-number" aria-hidden="true">3</span>Download</button></li>
        </ol>

        <div id="static-preview" class="eq-editor-preview" role="img" aria-label="Your QR code"></div>

        {{-- Beside the preview at every step, so a faint colour is seen where it was chosen. --}}
        <ul id="static-warnings" class="eq-editor-notices" role="status" aria-label="Scan check"></ul>

        <section class="eq-editor-panel" data-step="look" aria-label="Look">
            <p class="eq-editor-hint">Pick a look. Each one is drawn with your own link.</p>
            <div id="static-looks" class="eq-looks" role="group" aria-label="Look"></div>
            <p class="eq-editor-look">Look: <strong id="static-look-name">Rounded</strong></p>

            <details class="eq-more">
                <summary>More shapes and colours</summary>
                <x-qr-design-controls.shapes />
                <x-qr-design-controls.colours />
                <x-qr-design-controls.frame />
            </details>
        </section>

        <section class="eq-editor-panel" data-step="logo" aria-label="Logo" hidden>
            <p class="eq-editor-hint">Optional. Your logo stays on your device. Nothing is uploaded.</p>
            <x-qr-design-controls.logo />
        </section>

        <section class="eq-editor-panel" data-step="download" aria-label="Download" hidden>
            <div class="eq-editor-size">
                <label for="static-png-size" class="eq-label">PNG size</label>
                <select id="static-png-size" class="eq-input">
                    <option value="512">512 px</option>
                    <option value="1024" selected>1024 px</option>
                    <option value="2048">2048 px</option>
                    <option value="4096">4096 px</option>
                </select>
            </div>
            <p id="static-blocked" class="eq-editor-blocked" role="alert" hidden></p>
            <div class="eq-actions">
                <button type="button" id="static-download-png" class="eq-btn eq-btn-primary eq-btn--sm" aria-describedby="static-blocked">Download PNG</button>
                <button type="button" id="static-download-svg" class="eq-btn eq-btn-outline eq-btn--sm" aria-describedby="static-blocked">Download SVG</button>
                <button type="button" id="static-copy" class="eq-btn eq-btn-outline eq-btn--sm" aria-describedby="static-blocked" hidden>Copy as an image</button>
                <button type="button" id="static-share" class="eq-btn eq-btn-outline eq-btn--sm" aria-describedby="static-blocked" hidden>Share</button>
            </div>
            <p id="static-status" class="eq-editor-status" role="status"></p>
            <p class="eq-editor-hint">Scan it with your phone before you print.</p>
            <p class="eq-editor-hint">
                A static code can never be changed. Need to edit the link later or track scans?
                @auth
                    <a href="{{ route('filament.admin.resources.qr-codes.create') }}">Create a dynamic QR</a>
                @else
                    {{-- Tagged so its conversions can be told apart from the offer's below. --}}
                    <a href="{{ \App\Filament\Pages\Auth\Register::linkFrom(\App\Enums\SignupSource::StaticInline, $page) }}">Create a dynamic QR</a>
                @endauth
            </p>
        </section>

        <div class="eq-editor-nav">
            <button type="button" id="static-back" class="eq-btn eq-btn-outline eq-btn--sm" hidden>Back</button>
            <button type="button" id="static-next" class="eq-btn eq-btn-dark eq-btn--sm">Next</button>
        </div>
    </div>

    @if (! $slot->isEmpty())
        <div class="eq-card-grid">
            {{ $slot }}
        </div>
    @endif

</div>

{{--
    The offer, opened only once a download has actually started — see the
    script below. Absent for anyone signed in: they have an account and the
    pitch would be for something they already have.

    A real <dialog> rather than a styled div. showModal() brings the focus
    trap, Escape-to-close, inerting of the page behind it and restoration of
    focus on close — all of which this needs to be usable by keyboard and a
    screen reader, and all of which is easy to hand-roll subtly wrong.

    This began life as an inline panel below the result. It was invisible in
    practice: on a phone the result panel is tall enough to push it off
    screen entirely, and on a desktop a quiet white card below the fold went
    unnoticed too. A modal is the only version of this that is actually seen.
--}}
@guest
    <dialog id="static-offer" class="eq-offer" aria-labelledby="static-offer-title">
        <button type="button" id="static-offer-dismiss" class="eq-offer-close" aria-label="Close">&times;</button>

        <p class="eq-offer-kicker">Printing it?</p>

        <h2 id="static-offer-title" class="eq-offer-title">That code can never be changed</h2>

        <p class="eq-offer-text">
            The link is baked into the pattern. If the page moves or the offer
            behind it ends, every poster you printed points at a dead URL and the
            only fix is reprinting them.
        </p>

        <p class="eq-offer-text">
            A dynamic code points at us instead, so you can change where it
            goes whenever you like &mdash; and see how many people scanned it.
        </p>

        @php
            $offerPlan = \App\Support\SubscriptionPrice::cheapest();
            $offerSaving = \App\Support\SubscriptionPrice::saving(\App\Enums\Plan::Yearly);
            $offerAlternative = 'or '.\App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Yearly)
                .' a '.\App\Enums\Plan::Yearly->interval()->value
                .($offerSaving === null ? '' : ' — save '.$offerSaving).'.';
        @endphp

        <p class="eq-offer-price">
            {{ \App\Support\SubscriptionPrice::formatted($offerPlan) }}<span class="eq-offer-period">/{{ $offerPlan->interval()->value }}</span>
        </p>
        <p class="eq-offer-note">
            {{ $offerAlternative }}
            {{ config('subscription.trial_days') }}-day free trial, no payment details needed.
        </p>

        <div class="eq-offer-actions">
            <a id="static-offer-cta"
               href="{{ \App\Filament\Pages\Auth\Register::linkFrom(\App\Enums\SignupSource::StaticOffer, $page) }}"
               class="eq-btn eq-btn-primary eq-btn--sm">Start the free trial</a>
            <button type="button" id="static-offer-no" class="eq-offer-quiet">No thanks</button>
        </div>
    </dialog>
@endguest

@pushOnce('scripts')
    <script src="{{ \App\Support\Asset::versioned('js/qrcode-generator.js') }}"></script>
    <script src="{{ \App\Support\Asset::versioned('js/qr-renderer.js') }}"></script>
    <script src="{{ \App\Support\Asset::versioned('js/qr-design-controls.js') }}"></script>
    <script src="{{ \App\Support\Asset::versioned('js/qr-link.js') }}"></script>
    <script>
        (function () {
            const form = document.getElementById('static-qr-form');
            const input = document.getElementById('static-url');
            const message = document.getElementById('static-error');
            const result = document.getElementById('static-result');
            const preview = document.getElementById('static-preview');
            const looks = document.getElementById('static-looks');
            const lookName = document.getElementById('static-look-name');
            const warnings = document.getElementById('static-warnings');
            const blockedMessage = document.getElementById('static-blocked');
            const sizeSelect = document.getElementById('static-png-size');
            const downloadPng = document.getElementById('static-download-png');
            const downloadSvg = document.getElementById('static-download-svg');
            const copyButton = document.getElementById('static-copy');
            const shareButton = document.getElementById('static-share');
            const status = document.getElementById('static-status');
            const backButton = document.getElementById('static-back');
            const nextButton = document.getElementById('static-next');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            /*
             * The same prohibited lists the server applies to a dynamic code's
             * destination, rendered from the model's constants so there is one
             * list. public/js/qr-link.js applies them in the browser now that
             * the link is never sent anywhere.
             */
            const linkRules = @js([
                'domains' => \App\Models\QrCode::PROHIBITED_DOMAINS,
                'keywords' => \App\Models\QrCode::PROHIBITED_KEYWORDS,
                'extensions' => \App\Models\QrCode::PROHIBITED_EXTENSIONS,
            ]);

            /*
             * The page this generator is embedded on: `home`, or a Landing
             * Page slug. Read from the form rather than rendered into the
             * script, because the script is pushed once per page and must not
             * assume which page that is. Sent with every event, and checked
             * server-side against the pages that exist.
             */
            const page = form.dataset.page;

            /**
             * Reports one of the events that only this page can see. Event
             * names are rendered from the TrackedEvent enum rather than typed
             * here, so a renamed case cannot leave the page quietly posting a
             * value the endpoint no longer accepts.
             *
             * Fire and forget in both directions: the response is ignored, and a
             * failure is swallowed. Counting a download must never be the reason
             * a download does not happen. `keepalive` is what lets the request
             * survive if the click does navigate away.
             */
            function logEvent(event, extra) {
                /*
                 * The try wraps the call itself, not just the promise. `fetch` is
                 * routinely replaced by browser extensions and by injected dev
                 * tooling, and a replacement that throws synchronously used to
                 * take out whatever called this — which is how a failed count
                 * once stopped a dismissal from being remembered.
                 */
                try {
                    fetch('{{ route('events.log') }}', {
                        method: 'POST',
                        keepalive: true,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(Object.assign({ event: event, page: page }, extra || {})),
                    }).catch(function () {});
                } catch (e) {
                    // Counting must never be the reason something else fails.
                }
            }

            const offer = document.getElementById('static-offer');
            const offerDismiss = document.getElementById('static-offer-dismiss');
            const offerNoThanks = document.getElementById('static-offer-no');
            const offerCta = document.getElementById('static-offer-cta');

            /*
             * Dismissal is remembered in localStorage rather than a cookie. A
             * cookie for this would be a marketing cookie, which contradicts
             * section 2c of the Privacy Policy and would drag the whole site
             * behind a real consent gate for the sake of one dialog.
             *
             * The cost is worth stating: this is per-browser and invisible to us,
             * so the same person is asked again on their phone. The alternative
             * costs a consent banner.
             */
            const DISMISSED_KEY = 'eq.offer.dismissed';

            /*
             * Set when the call to action is taken, so the dialog closing on the
             * way to the register page is not also counted as a dismissal. One
             * click must not land in two buckets.
             */
            let offerAccepted = false;

            /*
             * Belt to localStorage's braces. Private windows and blocked storage
             * make the stored flag unavailable, and being asked again after
             * saying no is the most irritating thing this page could do. This
             * holds the refusal for the rest of the page view even when nothing
             * can be written down.
             */
            let offerDismissedThisView = false;

            function offerWasDismissed() {
                if (offerDismissedThisView) {
                    return true;
                }

                try {
                    return localStorage.getItem(DISMISSED_KEY) === '1';
                } catch (e) {
                    // Private mode, or storage disabled. Treat as not dismissed:
                    // showing the offer is the recoverable failure.
                    return false;
                }
            }

            /*
             * Opened a beat after the download rather than with it. The click
             * starts a file save, and a modal thrown up in the same tick lands on
             * top of the browser's own download UI — which reads as the offer
             * having interrupted the thing they came for, rather than following
             * it.
             */
            const REVEAL_DELAY_MS = 500;

            function revealOffer() {
                if (!offer || offer.open || offerWasDismissed()) {
                    return;
                }

                if (typeof offer.showModal === 'function') {
                    offer.showModal();
                } else {
                    // No native modal support. A plain open attribute still shows
                    // the card, without the focus trap or the backdrop; better
                    // than an offer nobody ever sees.
                    offer.setAttribute('open', '');
                }

                logEvent('{{ \App\Enums\TrackedEvent::OfferShown->value }}');
            }

            if (offer) {
                /*
                 * Every route out of the dialog ends in close(), so dismissal is
                 * counted in exactly one place. That includes the two buttons, the
                 * Escape key and a click on the backdrop — routes the previous
                 * inline version could not offer at all, and which would otherwise
                 * each need their own logging and their own bug.
                 */
                offer.addEventListener('close', function () {
                    if (offerAccepted) {
                        return;
                    }

                    /*
                     * Remembered before it is reported, and the order is the
                     * whole point. Not being asked again is a promise to the
                     * person who just said no; the count is only for us. This was
                     * the other way round once, and any throw out of the
                     * reporting call — a patched fetch, most likely — skipped the
                     * line below it, so the offer came back on the next
                     * download. The promise must not depend on the telemetry.
                     */
                    offerDismissedThisView = true;

                    try {
                        localStorage.setItem(DISMISSED_KEY, '1');
                    } catch (e) {
                        // Private mode or storage disabled. The in-memory flag
                        // above still holds for the rest of this page view.
                    }

                    logEvent('{{ \App\Enums\TrackedEvent::OfferDismissed->value }}');
                });

                offerDismiss.addEventListener('click', function () {
                    offer.close();
                });

                /*
                 * Named for the copy rather than the concept, deliberately.
                 * PublicPagesTest guards the promise that the cookie notice
                 * offers no refuse-cookies button by asserting that word does
                 * not appear on any public page, and this is a script comment,
                 * so anything written here ships in the HTML and trips it too.
                 */
                offerNoThanks.addEventListener('click', function () {
                    offer.close();
                });

                /*
                 * The dialog element fills the viewport when modal; its own box is
                 * the card. So a click landing on the element itself, rather than
                 * on anything inside it, is a click on the backdrop.
                 */
                offer.addEventListener('click', function (event) {
                    if (event.target === offer) {
                        offer.close();
                    }
                });

                /*
                 * Not preventDefault()'d: the link must navigate. logEvent uses
                 * keepalive precisely so the count survives the navigation.
                 */
                offerCta.addEventListener('click', function () {
                    offerAccepted = true;
                    logEvent('{{ \App\Enums\TrackedEvent::OfferClicked->value }}');
                });
            }

            /**
             * A download never reaches the server, so the click is the only
             * evidence it happened. The format tells the two buttons apart.
             */
            function handleDownload(format) {
                logEvent('{{ \App\Enums\TrackedEvent::QrDownloaded->value }}', { format: format });
                window.setTimeout(revealOffer, REVEAL_DELAY_MS);
            }

            const ERROR_DELAY_MS = 700;
            const SVG_PIXELS = 1024;
            const TOO_LONG = 'That link is too long to fit in a QR code.';

            let design = QrRenderer.defaultDesign();
            let logoSrc = null;
            let blocked = false;
            let link = null;
            let errorTimer = null;
            let reportedGenerated = false;

            function say(text, tone) {
                message.textContent = text;
                message.dataset.tone = tone;
            }

            function showStatus(text) {
                status.textContent = text;
            }

            const STEPS = ['look', 'logo', 'download'];

            function currentStep() {
                return document.querySelector('.eq-step[aria-current="step"]').dataset.step;
            }

            function paintNextButton() {
                nextButton.textContent = currentStep() === 'logo' && !design.logo ? 'Skip' : 'Next';
            }

            function showStep(name) {
                document.querySelectorAll('.eq-editor-panel').forEach(function (panel) {
                    panel.hidden = panel.dataset.step !== name;
                });

                document.querySelectorAll('.eq-step').forEach(function (step) {
                    step.setAttribute('aria-current', step.dataset.step === name ? 'step' : 'false');
                });

                backButton.hidden = name === STEPS[0];
                nextButton.hidden = name === STEPS[STEPS.length - 1];
                preview.classList.toggle('is-small', name !== 'download');
                paintNextButton();
                showStatus('');
            }

            function moveStep(direction) {
                showStep(STEPS[STEPS.indexOf(currentStep()) + direction]);
            }

            const lookButtons = {};

            Object.keys(QrRenderer.LOOKS).forEach(function (key) {
                const button = document.createElement('button');
                const thumb = document.createElement('span');
                const name = document.createElement('span');

                button.type = 'button';
                button.className = 'eq-look';
                button.dataset.look = key;
                thumb.className = 'eq-look-thumb';
                thumb.setAttribute('aria-hidden', 'true');
                name.className = 'eq-look-name';
                name.textContent = QrRenderer.LOOKS[key].label;
                button.append(thumb, name);

                button.addEventListener('click', function () {
                    design = QrRenderer.applyLook(design, key);
                    controls.update({ design: design });
                    draw();
                });

                looks.append(button);
                lookButtons[key] = button;
            });

            function paintLooks() {
                const active = QrRenderer.lookOf(design);

                Object.keys(lookButtons).forEach(function (key) {
                    const button = lookButtons[key];

                    button.setAttribute('aria-pressed', key === active ? 'true' : 'false');
                    button.querySelector('.eq-look-thumb').innerHTML = QrRenderer.render(link, QrRenderer.applyLook(Object.assign({}, design, { frame: 'none', logo: null }), key)).svg;
                });

                lookName.textContent = QrDesignControls.lookName(design);
            }

            /**
             * The scan check drives the page: warnings and blockers sit beside the
             * preview, and while the Design is blocked every way of getting the
             * code out is disabled with the reason stated.
             */
            function paintVerdict(verdict) {
                warnings.replaceChildren();

                verdict.reasons.forEach(function (reason) {
                    const item = document.createElement('li');

                    item.dataset.tone = 'blocked';
                    item.textContent = reason;
                    warnings.append(item);
                });

                verdict.warnings.forEach(function (warning) {
                    const item = document.createElement('li');

                    item.dataset.tone = 'warning';
                    item.textContent = warning;
                    warnings.append(item);
                });

                blockedMessage.hidden = !verdict.blocked;
                blockedMessage.textContent = verdict.blocked ? 'Downloading is off until this is fixed. ' + verdict.reasons.join(' ') : '';

                [downloadPng, downloadSvg, copyButton, shareButton].forEach(function (button) {
                    button.disabled = verdict.blocked;
                });
            }

            function hideCode() {
                link = null;
                result.hidden = true;
            }

            /**
             * Judges the field and either draws the code or says why not. Runs
             * on every keystroke; the code redraws as they type.
             */
            function draw() {
                const checked = QrLink.check(input.value, linkRules);

                window.clearTimeout(errorTimer);

                if (!checked.valid) {
                    hideCode();

                    if (checked.empty) {
                        say('Your QR code appears here as soon as you type a link.', 'hint');
                    } else {
                        say(checked.message, 'hint');
                        errorTimer = window.setTimeout(function () {
                            say(checked.message, 'error');
                        }, ERROR_DELAY_MS);
                    }

                    return;
                }

                let drawn;
                let verdict;

                try {
                    drawn = QrRenderer.render(checked.link, design, { logoSrc: logoSrc });
                } catch (e) {
                    hideCode();
                    say(TOO_LONG, 'error');

                    return;
                }

                link = checked.link;
                say('', 'hint');
                preview.innerHTML = drawn.svg;
                preview.style.aspectRatio = drawn.width + ' / ' + drawn.height;
                paintLooks();
                paintNextButton();
                verdict = QrDesignControls.verdict(design);
                blocked = verdict.blocked;
                paintVerdict(verdict);
                result.hidden = false;

                /*
                 * Counted once per page view, the first time a valid link is
                 * drawn. The event names the page and nothing else: the link
                 * is not part of it.
                 */
                if (!reportedGenerated) {
                    reportedGenerated = true;
                    logEvent('{{ \App\Enums\TrackedEvent::StaticQrGenerated->value }}');
                }
            }

            function pixelsWide() {
                return parseInt(sizeSelect.value, 10);
            }

            function svgBlob() {
                return new Blob([QrRenderer.render(link, design, { pixelWidth: SVG_PIXELS, logoSrc: logoSrc }).svg], { type: 'image/svg+xml' });
            }

            function pngBlob(width) {
                return new Promise(function (resolve, reject) {
                    const drawn = QrRenderer.render(link, design, { pixelWidth: width, logoSrc: logoSrc });
                    const height = Math.round(width * drawn.height / drawn.width);
                    const source = URL.createObjectURL(new Blob([drawn.svg], { type: 'image/svg+xml' }));
                    const image = new Image();

                    image.onload = function () {
                        const canvas = document.createElement('canvas');

                        canvas.width = width;
                        canvas.height = height;
                        canvas.getContext('2d').drawImage(image, 0, 0, width, height);
                        URL.revokeObjectURL(source);
                        canvas.toBlob(function (blob) {
                            return blob ? resolve(blob) : reject(new Error('empty'));
                        }, 'image/png');
                    };

                    image.onerror = function () {
                        URL.revokeObjectURL(source);
                        reject(new Error('unreadable'));
                    };

                    image.src = source;
                });
            }

            function save(blob, name) {
                const anchor = document.createElement('a');

                anchor.href = URL.createObjectURL(blob);
                anchor.download = name;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                window.setTimeout(function () {
                    URL.revokeObjectURL(anchor.href);
                }, 1000);
            }

            downloadPng.addEventListener('click', async function () {
                if (blocked) {
                    return;
                }

                try {
                    save(await pngBlob(pixelsWide()), 'qr-code.png');
                    showStatus('PNG downloaded.');
                    handleDownload('png');
                } catch (e) {
                    showStatus('Your browser could not make the PNG. Try the SVG instead.');
                }
            });

            downloadSvg.addEventListener('click', function () {
                if (blocked) {
                    return;
                }

                save(svgBlob(), 'qr-code.svg');
                showStatus('SVG downloaded.');
                handleDownload('svg');
            });

            /*
             * Copy and Share are offered only where the browser can do them,
             * so nobody is shown a button that cannot work.
             */
            const canCopy = !!(navigator.clipboard && typeof navigator.clipboard.write === 'function' && typeof window.ClipboardItem === 'function');

            function canShareFiles() {
                try {
                    return typeof navigator.share === 'function'
                        && typeof navigator.canShare === 'function'
                        && navigator.canShare({ files: [new File([''], 'qr-code.png', { type: 'image/png' })] });
                } catch (e) {
                    return false;
                }
            }

            copyButton.hidden = !canCopy;
            shareButton.hidden = !canShareFiles();

            copyButton.addEventListener('click', async function () {
                if (blocked) {
                    return;
                }

                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngBlob(pixelsWide()) })]);
                    showStatus('Copied as an image.');
                } catch (e) {
                    showStatus('Your browser would not copy the image. Download it instead.');
                }
            });

            shareButton.addEventListener('click', async function () {
                if (blocked) {
                    return;
                }

                try {
                    const file = new File([await pngBlob(pixelsWide())], 'qr-code.png', { type: 'image/png' });

                    await navigator.share({ files: [file], title: 'My QR code' });
                } catch (e) {
                    if (e && e.name !== 'AbortError') {
                        showStatus('Sharing did not work here. Download it instead.');
                    }
                }
            });

            document.querySelectorAll('.eq-step').forEach(function (step) {
                step.addEventListener('click', function () {
                    showStep(step.dataset.step);
                });
            });

            nextButton.addEventListener('click', function () {
                moveStep(1);
            });

            backButton.addEventListener('click', function () {
                moveStep(-1);
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
            });

            input.addEventListener('input', draw);

            /*
             * Every control lives inside the editor and reports here. The logo
             * picture is held in memory only: it is read from a local file and
             * drawn into the code, and is never sent anywhere.
             */
            const controls = QrDesignControls.mount(result, {
                design: design,
                logoSrc: logoSrc,
                onChange: function (state) {
                    design = state.design;
                    logoSrc = state.logoSrc;
                    draw();
                },
            });

            showStep('look');
            draw();
        })();
    </script>
@endPushOnce
