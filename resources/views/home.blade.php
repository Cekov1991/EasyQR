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

    <x-static-generator page="home">

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

    </x-static-generator>

@endsection
