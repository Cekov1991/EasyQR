@extends('layouts.site')

@section('title', config('app.name') . ' - Create your QR code')
@section('description', 'Create a free static QR code instantly, with no account needed. Upgrade to dynamic QR codes with editable destinations and scan analytics.')
@section('keywords', 'QR Code, Free QR Code Generator, Dynamic QR, Analytics, Editable QR Codes, Trackable QR Codes')
@section('shell_class', 'eq-shell--wide')

@push('structured-data')
    <script type="application/ld+json">{!! \App\Support\StructuredData::forProduct() !!}</script>
@endpush

@section('content')

    {{--
        Two columns on a desktop: the generator on the left, and a rail on the
        right with the dynamic offer and the promises in one line each, so a
        buyer sees all three without scrolling. The rail sticks only within
        this row, never over the full promises below. One column on a phone.
    --}}
    <div class="eq-home">

        <div class="eq-home-main">
            <div class="eq-hero eq-hero--start">
                <h1 class="eq-h1">Create your QR code</h1>
                <p class="eq-lead">
                    Choose static for a free, one-time code, or dynamic if you need to edit and track it later.
                </p>
            </div>

            <x-static-generator page="home" />
        </div>

        <aside class="eq-home-rail">

            {{-- Dynamic QR: teaser card, creation lives in the dashboard --}}
            <div class="eq-rail-card">
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
                    Tax included. <a href="{{ route('pricing') }}">See what’s included</a>
                </p>
                <p class="eq-card-text">
                    Change where it points after printing, and see how often it is scanned.
                </p>
                @auth
                    <a href="{{ route('filament.admin.resources.qr-codes.create') }}" class="eq-btn eq-btn-dark eq-btn--sm">Open Dashboard</a>
                @else
                    <a href="{{ route('filament.admin.auth.register') }}" class="eq-btn eq-btn-dark eq-btn--sm">Start the free trial</a>
                    <p class="eq-rail-quiet">or <a href="{{ route('filament.admin.auth.login') }}">log in</a></p>
                @endauth
            </div>

            <div class="eq-rail-promises">
                <h2 class="eq-rail-title">Before you pay</h2>
                <ul class="eq-rail-list">
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>{{ config('subscription.trial_days') }}-day trial, no card</li>
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>Monthly or yearly, cancel anytime</li>
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>14-day refund</li>
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>{{ config('subscription.grace_days') }}-day grace on a failed renewal</li>
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>Nothing deleted if you stop</li>
                    <li><span class="eq-rail-tick" aria-hidden="true">✓</span>No scanner IP stored</li>
                </ul>
                <a href="#guarantees" class="eq-rail-more">Read the promises in full</a>
            </div>

        </aside>

    </div>

    <x-guarantees />

@endsection
