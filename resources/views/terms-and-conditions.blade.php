@extends('layouts.site')

@section('title', 'Terms and Conditions - ' . config('app.name'))
@section('description', 'The terms that govern your use of ' . config('app.name') . '.')

@section('content')

    <div class="eq-prose">
        <h1 class="eq-h1">Terms and Conditions</h1>
        <p class="eq-prose-updated">
            Last updated 14 August 2026 · {{ config('site.domain') }} · Operated by {{ config('site.operator.name') }}
        </p>

        <h2>1. Introduction</h2>
        <p>
            Welcome to {{ config('site.domain') }} (“we,” “our,” or “us”). These Terms and Conditions (“Terms”)
            govern your use of our website, tools, and services (the “Service”).
        </p>
        <p>By using our Service, you agree to these Terms. If you do not agree, please do not use the Service.</p>

        <h2>2. Service Description</h2>
        <p>We provide an online QR code generator offering two kinds of code:</p>
        <ul>
            <li>
                <strong>Static QR codes</strong> are free and encode their destination directly in the image.
                They cannot be edited or tracked, and they keep working permanently whether or not you hold a
                subscription.
            </li>
            <li>
                <strong>Dynamic QR codes</strong> resolve through our servers, which lets you change the
                destination after printing and see how often the code was scanned. They require an account and
                an active trial or subscription.
            </li>
        </ul>
        <p>
            Supported dynamic formats include website link, WiFi, contact card, email, WhatsApp, SMS, phone call,
            location, and calendar event, together with basic styling such as colour and logo insertion.
            We do not currently offer advanced enterprise features, unless otherwise stated.
        </p>

        <h2>3. Eligibility</h2>
        <p>
            You must be at least 18 years old or have the permission of a legal guardian to use our Service.
            By using the Service, you confirm that you meet this requirement.
        </p>

        <h2>4. Account &amp; Usage</h2>
        <ul>
            <li>An account may be required for certain features. You are responsible for keeping your account secure.</li>
            <li>
                You agree not to use our Service for unlawful purposes, including but not limited to phishing,
                spreading malware, or linking to illegal content.
            </li>
            <li>We reserve the right to suspend or terminate your access if we believe your use violates these Terms.</li>
        </ul>
        <p>
            <strong>Reporting a code.</strong> Anyone can report a QR code that breaks these rules at
            <a href="{{ route('report.create') }}">{{ config('site.domain') }}/report</a>, with or without
            an account. We investigate every report. Where we find a code pointing at phishing, malware,
            illegal content, or material intended to harass, we disable it without notice and may suspend
            the account behind it.
        </p>
        <p>
            Because a dynamic code's destination can be changed after it has been printed and scanned, we
            may disable a code based on where it points at the time we review it, regardless of where it
            pointed when it was created.
        </p>

        <h2>5. Trial, Subscription &amp; Payments</h2>
        <ul>
            <li>
                New accounts include a {{ config('subscription.trial_days') }}-day free trial.
                No payment details are required to start it.
            </li>
            <li>
                After the trial, keeping dynamic QR codes active requires a subscription costing
                {{ \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Yearly) }}
                per year. Prices are in {{ \App\Support\SubscriptionPrice::currency() }} and include any applicable
                VAT or sales tax. Our <a href="{{ route('pricing') }}">Pricing page</a> sets this out in full.
            </li>
            <li>
                The subscription renews automatically each year until cancelled. You may cancel at any time from
                your account, and you keep access until the end of the period you have already paid for.
            </li>
            <li>
                Payments are processed by AgentaOS, which acts as merchant of record and issues your invoice
                and receipt.
            </li>
            <li>
                Prices may change, but never for a period you have already paid for. We will give notice before
                a changed price applies to a renewal.
            </li>
            <li>Refunds are covered by our <a href="{{ url('/refund-policy') }}">Refund Policy</a>.</li>
        </ul>

        <h2>6. QR Code Validity &amp; Data</h2>
        <ul>
            <li>
                Static QR codes are free, unlimited in time, and continue to work permanently. They encode their
                destination directly, so they do not depend on our service being available.
            </li>
            <li>Dynamic QR codes resolve through our servers, and do so only while your trial or subscription is active.</li>
            <li>
                If your subscription lapses, your dynamic QR codes stop redirecting and anyone scanning them sees
                a notice that the code is not active. Your codes, their destinations, and your analytics are
                retained and resume working when you subscribe again.
            </li>
            <li>
                An account may hold up to {{ config('subscription.quotas.dynamic') }} dynamic and
                {{ config('subscription.quotas.static') }} static QR codes.
            </li>
            <li>You are responsible for the content linked via your QR codes.</li>
        </ul>

        <h2>7. Analytics</h2>
        <p>Our Service may provide basic usage statistics for dynamic QR codes.</p>
        <p>These analytics are for informational purposes only and may not be 100% accurate.</p>

        <h2>8. Intellectual Property</h2>
        <ul>
            <li>
                All software, design, and branding on {{ config('site.domain') }} are owned by us and protected
                by copyright laws.
            </li>
            <li>
                You retain rights to the content you link to via our QR codes, but you grant us a non-exclusive
                license to store and display that content as necessary to operate the Service.
            </li>
        </ul>

        <h2>9. Limitation of Liability</h2>
        <p>We are not liable for any loss or damage resulting from:</p>
        <ul>
            <li>Service downtime or errors</li>
            <li>Expired or deactivated QR codes</li>
            <li>Third-party content linked via QR codes</li>
            <li>Incorrect or incomplete analytics data</li>
        </ul>
        <p>
            Our maximum liability shall not exceed the total amount you paid for the Service in the past 12 months.
        </p>

        <h2>10. Changes to the Service &amp; Terms</h2>
        <p>We may update or modify these Terms at any time.</p>
        <p>
            Any changes will be posted on this page with a new “Last updated” date. Continued use of the Service
            after changes means you accept the updated Terms.
        </p>

        <h2>11. Governing Law</h2>
        <p>
            These Terms are governed by and construed in accordance with the laws of The Republic of North Macedonia,
            and disputes are subject to the jurisdiction of the courts in The Republic of North Macedonia.
        </p>
        <p>
            <strong>Nothing in this section removes any protection you have as a consumer under the mandatory law
            of your country of residence.</strong> If you are a consumer in the European Union or the United
            Kingdom, you keep the right to bring proceedings in the courts of your own country, and to rely on
            the consumer protection law that applies there. Where that law gives you more than these Terms do,
            that law wins.
        </p>

        <h2>12. Contact</h2>
        <p>For any questions about these Terms, contact us at:</p>
        <ul>
            <li><strong>Operator:</strong> {{ config('site.operator.name') }}, trading as {{ config('app.name') }}</li>
            <li><strong>Email:</strong> <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a></li>
            <li><strong>Address:</strong> {{ config('site.operator.address') }}</li>
            @if (config('site.operator.tax_id'))
                <li><strong>Tax number:</strong> {{ config('site.operator.tax_id') }}</li>
            @endif
        </ul>
    </div>

@endsection
