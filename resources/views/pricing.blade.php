@extends('layouts.site')

@section('title', 'Pricing - ' . config('app.name'))
@section('description', 'Static QR codes are free forever. Dynamic QR codes you can edit and track cost ' . \App\Support\SubscriptionPrice::everyPriceInProse() . ', tax included, after a ' . config('subscription.trial_days') . '-day free trial.')

@push('structured-data')
    <script type="application/ld+json">{!! \App\Support\StructuredData::forProduct() !!}</script>
@endpush

@section('content')

    @php
        $yearlyPrice = \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Yearly);
        $monthlyPrice = \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Monthly);
        $yearlyPeriod = \App\Enums\Plan::Yearly->interval()->value;
        $monthlyPeriod = \App\Enums\Plan::Monthly->interval()->value;
        $saving = \App\Support\SubscriptionPrice::saving(\App\Enums\Plan::Yearly);
    @endphp

    <div class="eq-hero">
        <h1 class="eq-h1">Pricing</h1>
        <p class="eq-lead">
            Static QR codes are free forever. Dynamic codes — the ones you can edit after
            printing and track — are from {{ $monthlyPrice }} a {{ $monthlyPeriod }},
            or {{ $yearlyPrice }} a {{ $yearlyPeriod }}@if ($saving !== null), saving {{ $saving }}@endif.
        </p>
    </div>

    <div class="eq-card-grid">

        <div class="eq-card eq-card--choice">
            <div class="eq-card-head">
                <span class="eq-card-title">Static QR</span>
                <span class="eq-badge">FREE</span>
            </div>
            <p class="eq-price">Free<span class="eq-price-period">forever</span></p>
            <p class="eq-price-note">No account, no payment, no expiry.</p>
            <ul class="eq-check-list">
                <li><span class="eq-check">✓</span>Unlimited codes, generated on the spot</li>
                <li><span class="eq-check">✓</span>Instant download (PNG/SVG)</li>
                <li><span class="eq-check">✓</span>Keeps working permanently, with or without an account</li>
            </ul>
            <p class="eq-card-text">
                A static code encodes its destination directly, so it cannot be edited after
                you download it and there is nothing for us to track.
            </p>
            <div class="eq-card-foot">
                <a href="{{ route('welcome') }}" class="eq-btn eq-btn-primary eq-btn--sm">Create one now</a>
            </div>
        </div>

        <div class="eq-card eq-card--choice">
            <div class="eq-card-head">
                <span class="eq-card-title">Dynamic QR</span>
                <span class="eq-badge eq-badge--dark">PAID</span>
            </div>
            <p class="eq-price">
                {{ $yearlyPrice }}<span class="eq-price-period">per {{ $yearlyPeriod }}</span>
                @if ($saving !== null)
                    <span class="eq-badge">Save {{ $saving }}</span>
                @endif
            </p>
            <p class="eq-price eq-price--alt">
                {{ $monthlyPrice }}<span class="eq-price-period">per {{ $monthlyPeriod }}</span>
            </p>
            <p class="eq-price-note">
                {{ config('subscription.trial_days') }}-day free trial first. No payment
                details required to start it.
            </p>
            <ul class="eq-check-list">
                <li><span class="eq-check eq-check--ink">✓</span>Up to {{ config('subscription.quotas.dynamic') }} dynamic codes, editable after printing</li>
                <li><span class="eq-check eq-check--ink">✓</span>Up to {{ config('subscription.quotas.static') }} saved static codes</li>
                <li><span class="eq-check eq-check--ink">✓</span>Scan analytics: count, country, device, browser</li>
                <li><span class="eq-check eq-check--ink">✓</span>Website, WiFi, contact card, email, WhatsApp, SMS, phone, location, calendar</li>
                <li><span class="eq-check eq-check--ink">✓</span>Colour and logo styling</li>
            </ul>
            <div class="eq-panel eq-card-foot">
                @auth
                    <span style="font-size:13.5px" class="eq-muted">Manage your subscription in your dashboard</span>
                    <a href="{{ route('filament.admin.pages.billing') }}" class="eq-btn eq-btn-dark eq-btn--sm">Open Subscription</a>
                @else
                    <span style="font-size:13.5px" class="eq-muted">Start with the free trial — no card needed</span>
                    <a href="{{ route('filament.admin.auth.register') }}" class="eq-btn eq-btn-dark eq-btn--sm">Create an account</a>
                    <a href="{{ route('filament.admin.auth.login') }}" style="font-size:13.5px">or log in</a>
                @endauth
            </div>
        </div>

    </div>

    <div class="eq-prose">

        <h2>What you pay</h2>
        <p>
            <strong>{{ $yearlyPrice }} per {{ $yearlyPeriod }}</strong>, billed once a year,
            or <strong>{{ $monthlyPrice }} per {{ $monthlyPeriod }}</strong>, billed every month.
            @if ($saving !== null)Paying yearly costs {{ $saving }} less than twelve monthly payments.@endif
            Whichever you choose, the plan renews automatically until you cancel it.
            Prices are in {{ \App\Support\SubscriptionPrice::currency() }}.
        </p>
        <p>
            <strong>Tax is included.</strong> AgentaOS is the merchant of record for every
            purchase and handles VAT or sales tax for your country, so the price above is the
            total you pay — nothing is added at checkout. Your invoice and receipt come from
            AgentaOS, and the charge appears on your statement under their name.
        </p>

        <h2>The free trial</h2>
        <p>
            Every new account starts with
            <strong>{{ config('subscription.trial_days') }} days of full access</strong>, and
            we ask for no payment details to begin it. Nothing is charged when the trial ends;
            if you do not subscribe, your dynamic codes simply stop resolving and everything
            stays in your account waiting for you.
        </p>

        <h2>Cancelling and refunds</h2>
        <p>
            Cancel at any time from the Subscription page in your account. Cancelling stops
            the next renewal, and you keep full access until the end of the period you have
            already paid for.
        </p>
        <p>
            If you subscribe and change your mind, you have 14 days to ask for a full refund
            on your first payment on a plan. The details are in our
            <a href="{{ url('/refund-policy') }}">Refund Policy</a>.
        </p>

        <h2>What happens if I stop paying?</h2>
        <p>
            Your <strong>static codes are unaffected</strong> — they encode their destination
            directly, so they never depend on us and keep working permanently. Your dynamic
            codes stop redirecting and anyone scanning them sees a notice that the code is not
            active. Your codes, their destinations and your analytics are all retained, and
            they resume working the moment you subscribe again.
        </p>

        <p class="eq-prose-updated">
            More answers in our <a href="{{ route('faq') }}">FAQ</a>, and the full terms in our
            <a href="{{ url('/terms-and-conditions') }}">Terms and Conditions</a>.
        </p>

    </div>

@endsection
