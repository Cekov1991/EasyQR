<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | The address shown in the footer and named in the Terms, Privacy Policy and
    | Refund Policy. It was previously hardcoded in seven places, which meant a
    | change had to be made in seven places and the legal notice address could
    | silently drift from the one in the footer.
    |
    | This is a public, legally-cited address, so it should be on our own
    | domain rather than a free mailbox.
    |
    */

    'support_email' => env('SITE_SUPPORT_EMAIL', 'support@easy-qr-code.com'),

    /*
    |--------------------------------------------------------------------------
    | Domain
    |--------------------------------------------------------------------------
    |
    | The public domain, as named in the Terms and the Privacy Policy. Those
    | documents identify the Service by this name, so it must match the domain
    | customers actually reach.
    |
    */

    'domain' => env('SITE_DOMAIN', 'easy-qr-code.com'),

    /*
    |--------------------------------------------------------------------------
    | Operator
    |--------------------------------------------------------------------------
    |
    | Who is legally behind the service: the contracting party in the Terms and
    | the data controller in the Privacy Policy.
    |
    | This must match the account holder at AgentaOS. They are our merchant of
    | record, so the question their review answers is who they are selling on
    | behalf of — one identity here against another there is exactly the mismatch
    | that stalls the application.
    |
    | The service is operated by a registered company, so the name must be the
    | one in the central registry, including the legal form. Not the trading name:
    | "Easy QR Code" is the product and "Mayst Impact" the brand, but neither is
    | the contracting party.
    |
    | This was briefly set to an individual, on the plan of applying as one. The
    | company won on liability rather than tax — a QR redirect service is a
    | phishing vector by construction, and the entity is what keeps a victim's
    | claim away from a private person's assets.
    |
    | Was 'company' with a default of "Mayst Impact", unqualified. If
    | SITE_COMPANY_NAME or SITE_COMPANY_ADDRESS are still set in a deployed .env
    | they are ignored; delete them so they cannot mislead later.
    |
    */

    'operator' => [
        'name' => env('SITE_OPERATOR_NAME', 'Mayst Impact DOOEL'),
        'address' => env('SITE_OPERATOR_ADDRESS', 'Vladimir Komarov 25/4-16, Skopje, North Macedonia'),
        'tax_id' => env('SITE_OPERATOR_TAX_ID', '4032020546119'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processors
    |--------------------------------------------------------------------------
    |
    | Every company that processes personal data on our behalf, as named in the
    | Privacy Policy. The policy renders this list rather than repeating it in
    | prose, so a processor cannot be added to the stack without appearing in the
    | document — and cannot be dropped from the document while still in use.
    |
    | Adding a row here is a disclosure, not a config change. If you add a
    | processor, confirm its data processing agreement incorporates the Standard
    | Contractual Clauses before shipping, because section 6 of the policy relies
    | on them for transfers out of the EEA.
    |
    */

    'processors' => [
        [
            'name' => 'Laravel Cloud',
            'role' => 'Application hosting and database',
            'location' => 'United States',
        ],
        [
            'name' => 'Cloudflare',
            'role' => 'Content delivery, TLS, bot protection, and the approximate country of a QR code scan',
            'location' => 'Global edge network',
        ],
        [
            'name' => 'Resend',
            'role' => 'Delivery of account and billing email',
            'location' => 'United States',
        ],
        [
            'name' => 'AgentaOS',
            'role' => 'Payment processing as merchant of record',
            'location' => 'See their privacy policy',
        ],
        [
            'name' => 'Bunny Fonts',
            'role' => 'Serving the web fonts used on our public pages',
            'location' => 'European Union',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan retention
    |--------------------------------------------------------------------------
    |
    | How many months of QR code scan records are kept before `scans:prune`
    | deletes them.
    |
    | This number is published in section 7 of the Privacy Policy, which renders
    | it from here rather than restating it. So it is not a tuning knob: shortening
    | it is always safe, but lengthening it changes a commitment already made to
    | the people in those rows — strangers who scanned a poster and have no
    | account with us.
    |
    | Long enough for a subscriber to compare a campaign against the same month
    | last year; short enough that we are not holding third-party scan data
    | indefinitely.
    |
    */

    'scan_retention_months' => (int) env('SITE_SCAN_RETENTION_MONTHS', 24),

    /*
    |--------------------------------------------------------------------------
    | Event retention
    |--------------------------------------------------------------------------
    |
    | How many days of anonymous site event counts are kept before
    | `events:prune` deletes them. Like the scan window above, this number is
    | rendered into the Privacy Policy rather than restated there, so it cannot
    | drift away from what the code enforces.
    |
    | Far shorter than the scan window because the purpose is different: these
    | rows answer "is the funnel working this quarter", not "how did this
    | campaign do against last year". They also accrue much faster.
    |
    */

    'event_retention_days' => (int) env('SITE_EVENT_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Abandoned logo grace period
    |--------------------------------------------------------------------------
    |
    | How many hours an uploaded centre logo may sit unreferenced before
    | `logos:prune` deletes it.
    |
    | A guest on the public form uploads the logo before the QR code exists: the
    | record is only created once they finish registering, so between those two
    | moments the file is legitimately owned by nobody. The window has to outlast
    | that gap — session lifetime plus however long someone takes to find the
    | verification email — or the prune deletes a logo out from under a
    | registration still in progress.
    |
    */

    'orphan_logo_grace_hours' => (int) env('SITE_ORPHAN_LOGO_GRACE_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Link previews
    |--------------------------------------------------------------------------
    |
    | What WhatsApp, Slack, iMessage and X show when someone pastes a link to us.
    |
    | The description is also the <meta name="description"> fallback, so the two
    | cannot drift apart. A page overrides both at once by defining a
    | `description` section, and the same is true of the title.
    |
    | The card is a static PNG committed to the repo, not something generated per
    | request: previews are fetched by crawlers that will not wait on our queue,
    | and a link shared before the image existed is cached without one.
    |
    | Until this existed there was no og:image at all, so WhatsApp fell back to
    | the apple-touch-icon, which was still the Bootstrap starter template's
    | logo. Every link anyone shared was branded with someone else's mark. If you
    | replace the image, keep the dimensions below in step with the file: X and
    | LinkedIn read them rather than downloading the image to measure it.
    |
    */

    'share' => [
        'description' => 'Create free static QR codes instantly, or dynamic QR codes you can edit and track.',
        'image' => 'images/og-image.png',
        'image_width' => 1200,
        'image_height' => 630,
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer credit
    |--------------------------------------------------------------------------
    |
    | The optional "Powered by" line. The credit renders only when the URL is
    | set, so a blank URL drops it from the footer entirely.
    |
    | Off by default. It was switched off when the legal pages named an individual
    | and this line pointed at a company on a different domain, which read as two
    | identities on one page. That objection is gone now that Mayst Impact is the
    | operator, but the line has become redundant instead: the footer would credit
    | the same entity the Terms already name as the contracting party.
    |
    | Nothing here is permanent: set SITE_CREDIT_URL to bring it back.
    |
    */

    'credit' => [
        'name' => env('SITE_CREDIT_NAME', 'Mayst Impact'),
        'url' => env('SITE_CREDIT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing Pages
    |--------------------------------------------------------------------------
    |
    | Slugs to treat as published whatever App\Support\LandingPages says. Empty
    | in every real environment, and deliberately not read from the environment:
    | a page is published by a person flipping its flag in the registry after
    | reading the copy. This exists so a test can render a page still in review.
    |
    */

    'landing_pages' => [
        'publish' => [],
    ],

];
