@extends('layouts.site')

@section('title', config('app.name') . ' - Create your QR code')
@section('description', 'Create a free static QR code instantly, with no account needed. Upgrade to dynamic QR codes with editable destinations and scan analytics.')
@section('keywords', 'QR Code, Free QR Code Generator, Dynamic QR, Analytics, Editable QR Codes, Trackable QR Codes')

@push('structured-data')
    <script type="application/ld+json">{!! \App\Support\StructuredData::forProduct() !!}</script>
@endpush

@section('content')

    <div class="eq-hero">
        <h1 class="eq-h1">Create your QR code</h1>
        <p class="eq-lead">
            Choose static for a free, one-time code, or dynamic if you need to edit and track it later.
        </p>
    </div>

    <div class="eq-card-grid">

        {{-- Static QR: free, generated on the fly, never stored --}}
        <div class="eq-card eq-card--choice">
            <div class="eq-card-head">
                <span class="eq-card-title">Static QR</span>
                <span class="eq-badge">FREE</span>
            </div>
            <p class="eq-price">Free<span class="eq-price-period">forever</span></p>
            <p class="eq-price-note">No account, no payment, no expiry.</p>
            <p class="eq-card-text">
                Generated instantly and shown on screen. We never store your code or its link. Refresh the page and it’s gone, so download it right away.
            </p>
            <ul class="eq-check-list">
                <li><span class="eq-check">✓</span>No account needed</li>
                <li><span class="eq-check">✓</span>Keeps working permanently</li>
                <li><span class="eq-check">✓</span>Instant download (PNG/SVG)</li>
            </ul>
            <form id="static-qr-form" class="eq-card-form">
                <label for="static-url" class="eq-label">Website or link</label>
                <input type="text" id="static-url" class="eq-input" placeholder="https://example.com" autocomplete="off">
                <button type="submit" id="static-generate" class="eq-btn eq-btn-primary">Generate QR Code</button>
                <p id="static-error" class="eq-error"></p>
            </form>
        </div>

        {{-- Dynamic QR: teaser card, creation lives in the dashboard --}}
        <div class="eq-card eq-card--choice">
            <div class="eq-card-head">
                <span class="eq-card-title">Dynamic QR</span>
                <span class="eq-badge eq-badge--dark">PAID</span>
            </div>
            {{-- One figure, with a "from". The pricing page carries the comparison. --}}
            @php
                $cheapestPlan = \App\Support\SubscriptionPrice::cheapest();
            @endphp
            <p class="eq-price">
                <span class="eq-price-period">from</span>{{ \App\Support\SubscriptionPrice::formatted($cheapestPlan) }}<span class="eq-price-period">per {{ $cheapestPlan->interval()->value }}</span>
            </p>
            <p class="eq-price-note">
                {{ config('subscription.trial_days') }}-day free trial, no payment details needed.
                Tax included. <a href="{{ route('pricing') }}">See what’s included</a>
            </p>
            <p class="eq-card-text">
                Saved to your account. Change where it points anytime without reprinting, and see how many times it’s been scanned.
            </p>
            <ul class="eq-check-list">
                <li><span class="eq-check eq-check--ink">✓</span>Editable destination, anytime</li>
                <li><span class="eq-check eq-check--ink">✓</span>Scan tracking &amp; analytics</li>
                <li><span class="eq-check eq-check--ink">✓</span>Cancel anytime, keep access to period end</li>
            </ul>
            <div class="eq-panel eq-card-foot">
                @auth
                    <span style="font-size:13.5px" class="eq-muted">Create and manage dynamic QRs in your dashboard</span>
                    <a href="{{ route('filament.admin.resources.qr-codes.create') }}" class="eq-btn eq-btn-dark eq-btn--sm">Open Dashboard</a>
                @else
                    <span style="font-size:13.5px" class="eq-muted">Log in to generate a dynamic QR</span>
                    <a href="{{ route('filament.admin.auth.login') }}" class="eq-btn eq-btn-dark eq-btn--sm">Log In</a>
                    <a href="{{ route('filament.admin.auth.register') }}" style="font-size:13.5px">or create an account</a>
                @endauth
            </div>
        </div>

    </div>

    {{-- QR result: lives outside the cards so generating never changes their height --}}
    <div id="static-result" class="eq-result-panel" hidden>
        <img id="static-qr-img" src="" alt="Your QR code">
        <div class="eq-result-info">
            <h2 class="eq-h2">Your QR code is ready</h2>
            <p>Scan it with your phone to test it, then download it. We never store your code or its link, so once you leave this page it’s gone.</p>
            <div class="eq-actions">
                <a id="static-download-png" href="#" download="qr-code.png" class="eq-btn eq-btn-primary eq-btn--sm">Download PNG</a>
                <a id="static-download-svg" href="#" download="qr-code.svg" class="eq-btn eq-btn-outline eq-btn--sm">Download SVG</a>
            </div>
            <p style="font-size:13px">
                A static code can never be changed. Need to edit the link later or track scans?
                @auth
                    <a href="{{ route('filament.admin.resources.qr-codes.create') }}">Create a dynamic QR</a>
                @else
                    {{-- Tagged so its conversions can be told apart from the offer's below. --}}
                    <a href="{{ route('filament.admin.auth.register', ['ref' => \App\Enums\SignupSource::StaticInline->value]) }}">Create a dynamic QR</a>
                @endauth
            </p>
        </div>
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
                   href="{{ route('filament.admin.auth.register', ['ref' => \App\Enums\SignupSource::StaticOffer->value]) }}"
                   class="eq-btn eq-btn-primary eq-btn--sm">Start the free trial</a>
                <button type="button" id="static-offer-no" class="eq-offer-quiet">No thanks</button>
            </div>
        </dialog>
    @endguest

@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('static-qr-form');
            const input = document.getElementById('static-url');
            const button = document.getElementById('static-generate');
            const error = document.getElementById('static-error');
            const result = document.getElementById('static-result');
            const image = document.getElementById('static-qr-img');
            const downloadPng = document.getElementById('static-download-png');
            const downloadSvg = document.getElementById('static-download-svg');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            /**
             * Reports one of the four events that only this page can see. Event
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
                        body: JSON.stringify(Object.assign({ event: event }, extra || {})),
                    }).catch(function () {});
                } catch (e) {
                    // Counting must never be the reason something else fails.
                }
            }

            function showError(message) {
                error.textContent = message;
                error.style.display = 'block';
                result.hidden = true;
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

            /*
             * A download is a click on a data: URI, which never reaches the
             * server — so the click is the only evidence it happened. The
             * listeners are attached once, not per generation, and the format
             * tells the two buttons apart.
             */
            function handleDownload(format) {
                logEvent('{{ \App\Enums\TrackedEvent::QrDownloaded->value }}', { format: format });
                window.setTimeout(revealOffer, REVEAL_DELAY_MS);
            }

            downloadPng.addEventListener('click', function () {
                handleDownload('png');
            });

            downloadSvg.addEventListener('click', function () {
                handleDownload('svg');
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const url = input.value.trim();
                if (!url) {
                    showError('Please enter a URL.');
                    return;
                }

                button.disabled = true;
                button.textContent = 'Generating…';
                error.style.display = 'none';

                try {
                    const response = await fetch('{{ route('qr.instant') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ url: url }),
                    });

                    const data = await response.json().catch(() => null);

                    if (!response.ok) {
                        if (response.status === 429) {
                            showError('Too many requests. Please wait a minute and try again.');
                        } else {
                            showError((data && (data.errors?.url?.[0] || data.message)) || 'Something went wrong. Please try again.');
                        }
                        return;
                    }

                    image.src = data.png;
                    downloadPng.href = data.png;
                    downloadSvg.href = data.svg;
                    result.hidden = false;
                    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } catch (e) {
                    showError('Something went wrong. Please try again.');
                } finally {
                    button.disabled = false;
                    button.textContent = 'Generate QR Code';
                }
            });
        })();
    </script>
@endpush
