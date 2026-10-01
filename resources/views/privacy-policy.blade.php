@extends('layouts.site')

@section('title', 'Privacy Policy - ' . config('app.name'))
@section('description', 'How ' . config('app.name') . ' collects, uses, and protects your personal information.')

@section('content')

    <div class="eq-prose">
        <h1 class="eq-h1">Privacy Policy</h1>
        <p class="eq-prose-updated">Last updated 14 August 2026 · {{ config('site.domain') }}</p>

        <h2>Who we are</h2>
        <p>
            {{ config('site.operator.name') }}, a company registered in the Republic of North Macedonia,
            operates {{ config('site.domain') }} and is the <strong>data controller</strong> for the personal
            data described in this Policy. That means {{ config('site.operator.name') }} decides what is
            collected and why, and is the party legally answerable for it.
        </p>
        <ul>
            <li><strong>Controller:</strong> {{ config('site.operator.name') }}</li>
            <li><strong>Address:</strong> {{ config('site.operator.address') }}</li>
            <li><strong>Email:</strong> <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></li>
            @if (config('site.operator.tax_id'))
                <li><strong>Tax number:</strong> {{ config('site.operator.tax_id') }}</li>
            @endif
        </ul>

        <h2>1. Introduction</h2>
        <p>
            This Privacy Policy explains what personal data we collect when you use
            {{ config('site.domain') }}, why we collect it, who we share it with, and how long we keep it.
        </p>
        <p>
            It covers two different groups of people: <strong>account holders</strong>, who register with us
            to create QR codes, and <strong>people who scan a QR code</strong> made with our service, who
            have no account and no relationship with us. Section 3 is written for the second group.
        </p>

        <h2>2. Information We Collect</h2>

        <h3>a) Information you provide</h3>
        <p>When you register for an account, we collect:</p>
        <ul>
            <li>Name (first and/or last)</li>
            <li>Email address</li>
            <li>Password, stored only as a cryptographic hash — we cannot see or recover your actual password</li>
            <li>
                Which part of our site sent you to the registration form, if you arrived by following one of
                our own links — for example the upgrade offer shown after you download a free QR code. This is
                a single short label chosen from a fixed list we publish, such as
                <code>static-offer</code>. It records where the link was, never anything about you, and it is
                blank for most accounts. We use it only to understand which parts of the site people find
                useful, and it is deleted with your account.
            </li>
        </ul>
        <p>
            You also provide the content of the QR codes you create, which may include website addresses,
            contact details, WiFi credentials, message text, calendar events, locations, and any logo image
            you upload.
        </p>

        <h3>b) Information collected automatically</h3>
        <p>When you use our website while signed in, our systems record:</p>
        <ul>
            <li>
                A session record holding your IP address and browser user agent, which is how we keep you
                signed in and can tell one signed-in browser from another
            </li>
            <li>
                Your IP address on each request, used momentarily to apply rate limits and block abuse.
                Cloudflare sits in front of our servers and processes the same address for the same purpose.
            </li>
        </ul>
        <p>
            <strong>We run no third-party tracking on this site and build no profile of you.</strong> We do
            not record which pages you visit or how long you spend on them, and no advertising or analytics
            company receives anything about you from us.
        </p>
        <p>
            We do keep <strong>anonymous counts</strong> of how often certain things happen here — how many
            free QR codes were generated today, for instance, or how many people opened a checkout. A count
            records that something happened and never who it happened to: it carries no name, no account, no
            IP address, no cookie and nothing you typed, and the address you put into a free QR code is never
            part of it. A count may note which of our own pages it happened on, such as the homepage or one
            of our guides, but loading a page is never counted by itself. These counts cannot be traced back
            to you, connected to one another, or used to recognise you on a later visit. We keep them for
            <strong>{{ config('site.event_retention_days') }} days</strong> and use them to understand
            whether the site works, not who is using it.
        </p>

        <h3>c) Cookies</h3>
        <p>
            We use only the cookies needed to run the site: a session cookie that keeps you signed in,
            a security cookie that protects forms against cross-site request forgery, and a cookie that
            records that you have seen our cookie notice.
        </p>
        <p>
            <strong>We do not use advertising or analytics cookies.</strong> Because every cookie we set is
            strictly necessary to provide the service you asked for, there is nothing here to consent to or
            refuse — if we ever add tracking, we will ask first.
        </p>

        <h2>3. QR Code Scan Data</h2>
        <p>
            This section is for you if you scanned a QR code and want to know what was recorded. You do not
            have an account with us and we have no other relationship with you.
        </p>
        <p>
            Our service offers two kinds of code. <strong>Static</strong> codes encode their destination
            directly in the image, so scanning one never contacts us and we learn nothing about it.
            <strong>Dynamic</strong> codes resolve through our servers, so scanning one is a request to us,
            and we record that it happened so the person who created the code can see how it is performing.
        </p>
        <p>For each scan of a dynamic code we record:</p>
        <ul>
            <li>The date and time</li>
            <li>The approximate country, which Cloudflare determines from the network connection</li>
            <li>The device type, operating system and browser your device reports</li>
            <li>The referring page, where your browser supplies one</li>
        </ul>
        <p>
            <strong>We do not store the IP address of anyone who scans a QR code.</strong> Your address
            reaches our servers, as it must for any web request, and we use it in the moment to apply rate
            limits — but it is not written to the scan record and we cannot go back and look it up.
        </p>
        <p>
            This information is visible to the person who created the code, both as totals and as individual
            scan records. It is not connected to a name or an account, and we do not use it to recognise you
            across different codes or on a later visit.
        </p>
        <p>
            We do not sell it, share it with advertisers, or use it for advertising of any kind. The legal
            basis is our legitimate interest in providing the scan statistics that our subscribers pay for.
            If you would rather not be counted, you can decline to scan a code you do not trust — and if you
            believe a code is being used to mislead or harm people, please
            <a href="mailto:{{ config('site.support_email') }}">tell us</a>.
        </p>
        <p>
            You can ask us what we hold about you. Please bear in mind that because we deliberately hold
            nothing that identifies you, we will usually be unable to single out your individual scan from
            everyone else's.
        </p>

        <h2>4. Why We Use Your Information, and Our Legal Basis</h2>
        <p>
            Where the GDPR or a comparable law applies, we must have a lawful basis for each purpose. Ours
            are:
        </p>
        <div class="eq-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Legal basis</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Creating and managing your account</td>
                        <td>Performance of a contract</td>
                    </tr>
                    <tr>
                        <td>Generating, storing and resolving your QR codes</td>
                        <td>Performance of a contract</td>
                    </tr>
                    <tr>
                        <td>Taking payment and handling renewals</td>
                        <td>Performance of a contract</td>
                    </tr>
                    <tr>
                        <td>Account, security and billing email</td>
                        <td>Performance of a contract</td>
                    </tr>
                    <tr>
                        <td>Responding to your support requests</td>
                        <td>Performance of a contract</td>
                    </tr>
                    <tr>
                        <td>Scan statistics shown to the code's owner</td>
                        <td>Legitimate interest — providing the feature the owner subscribed for</td>
                    </tr>
                    <tr>
                        <td>Rate limiting, abuse prevention and keeping the service available</td>
                        <td>Legitimate interest — operating a lawful and reliable service</td>
                    </tr>
                    <tr>
                        <td>Product news and promotions</td>
                        <td>Consent, which you may withdraw at any time</td>
                    </tr>
                    <tr>
                        <td>Keeping business records required by tax and accounting law</td>
                        <td>Legal obligation</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p><strong>We do not sell your personal data, and we do not share it with advertisers.</strong></p>

        <h2>5. Who Processes Your Data</h2>
        <p>
            We use a small number of companies to run the service. Each processes personal data only on our
            instructions and only as far as its role requires.
        </p>
        <div class="eq-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>What they do for us</th>
                        <th>Where</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (config('site.processors') as $processor)
                        <tr>
                            <td><strong>{{ $processor['name'] }}</strong></td>
                            <td>{{ $processor['role'] }}</td>
                            <td>{{ $processor['location'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p>
            <strong>A note on payments.</strong> AgentaOS acts as the merchant of record for every purchase.
            When you subscribe you are buying from AgentaOS rather than from us, and they collect and hold
            your payment details, billing address and tax location directly. <strong>We never see or store
            your card details.</strong> They issue your invoice and receipt and are the controller for that
            payment data, so their own privacy policy governs it. We receive only a subscription reference,
            its status, and its renewal date.
        </p>
        <p>
            Beyond these processors, we disclose personal data only to legal or regulatory authorities where
            the law requires it.
        </p>

        <h2>6. Where Your Data Is Stored</h2>
        <p>
            Our application and database are hosted by Laravel Cloud on servers in the
            <strong>United States</strong>. Email is delivered through Resend, also in the United States.
            Cloudflare operates a global edge network, so your request may pass through a Cloudflare location
            near you before it reaches our servers.
        </p>
        <p>
            If you are in the European Economic Area, the United Kingdom or Switzerland, this means your
            personal data is transferred outside your region. Where such a transfer is not covered by an
            adequacy decision, we rely on the European Commission's <strong>Standard Contractual
            Clauses</strong> as the legal mechanism for it.
        </p>

        <h2>7. How Long We Keep It</h2>
        <div class="eq-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>How long</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Account details (name, email, password hash, and where you signed up from)</td>
                        <td>For as long as your account exists</td>
                    </tr>
                    <tr>
                        <td>Your QR codes and their destinations</td>
                        <td>For as long as your account exists; deleted with it</td>
                    </tr>
                    <tr>
                        <td>QR code scan records</td>
                        <td>
                            <strong>{{ config('site.scan_retention_months') }} months</strong>, then deleted
                            automatically. Sooner if the QR code itself is deleted, which removes its scans
                            with it
                        </td>
                    </tr>
                    <tr>
                        <td>Anonymous usage counts</td>
                        <td>
                            <strong>{{ config('site.event_retention_days') }} days</strong>, then deleted
                            automatically. A count records that something happened, never who it happened to,
                            so there is nothing in one to connect to you
                        </td>
                    </tr>
                    <tr>
                        <td>Sign-in session records</td>
                        <td>A session expires after {{ config('session.lifetime') }} minutes of inactivity</td>
                    </tr>
                    <tr>
                        <td>Subscription records</td>
                        <td>As long as tax and accounting law requires. AgentaOS holds the invoice itself, as merchant of record</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            When you delete your account we remove your personal details and your QR codes from our active
            systems, except anything the law requires us to keep. Copies inside encrypted backups disappear
            as those backups age out of rotation.
        </p>

        <h2>8. Email Communication</h2>
        <p>By creating an account, you agree to receive:</p>
        <ul>
            <li>
                Service email we cannot reasonably operate without — confirming your account, resetting your
                password, warning you that your trial is ending, and telling you when a payment fails
            </li>
            <li>
                Product updates or promotions, only if you opt in. You can unsubscribe from these at any time
                without losing access to anything.
            </li>
        </ul>

        <h2>9. Your Rights</h2>
        <p>Depending on where you live — for example under the GDPR or the CCPA — you have the right to:</p>
        <ul>
            <li>Access the data we hold about you</li>
            <li>Request correction or deletion of your data</li>
            <li>Restrict or object to certain processing, including our legitimate-interest processing</li>
            <li>Request a copy of your data in a portable format</li>
            <li>Withdraw consent for marketing email</li>
            <li>Not be discriminated against for exercising any of these rights</li>
        </ul>
        <p>
            To exercise any of them, email
            <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a>.
            We will not charge you for it and we aim to respond within 30 days.
        </p>
        <p>
            You also have the right to <strong>lodge a complaint with a data protection supervisory
            authority</strong>. If you are in the EEA or the UK, that is the authority in your country of
            residence. We would rather you came to us first so we can put things right, but you do not have
            to.
        </p>

        <h2>10. Data Security</h2>
        <p>We take reasonable measures to protect your information, including:</p>
        <ul>
            <li>Passwords stored as one-way hashes, never in a readable form</li>
            <li>All traffic encrypted in transit over HTTPS</li>
            <li>Restricted database access</li>
            <li>Card details never touching our servers — AgentaOS handles payment data</li>
        </ul>
        <p>However, no online service can be completely secure, and we do not claim otherwise.</p>

        <h2>11. Children</h2>
        <p>
            The service is not intended for children. You must be at least 18 years old to create an account,
            as set out in our <a href="{{ url('/terms-and-conditions') }}">Terms and Conditions</a>. We do not
            knowingly collect personal data from children. If you believe a child has given us personal data,
            contact us and we will delete it.
        </p>

        <h2>12. Changes to This Policy</h2>
        <p>
            We may update this Privacy Policy from time to time. Any change is posted here with a new "last
            updated" date, and if a change materially affects your rights we will notify you by email or
            through the website before it takes effect.
        </p>

        <h2>13. Contact Us</h2>
        <p>If you have any question about this Policy or about your data, contact us at:</p>
        <ul>
            <li><strong>Email:</strong> <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></li>
            <li><strong>Address:</strong> {{ config('site.operator.address') }}</li>
        </ul>
    </div>

@endsection
