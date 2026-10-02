@extends('layouts.site')

{{--
    The shared frame of every Landing Page: the nine sections in the order the
    spec sets, so no page can drop the pricing, the honesty about lapse or the
    FAQ markup by forgetting them.

    A page view supplies only what is written for its case: `answer`, the one
    sentence under the H1; `problem`, its scenario; `comparison`, what static
    and dynamic mean for that case; and optionally `steps`, when the three
    generic steps would read wrong. Everything with a price, a period or a
    quota in it is rendered here from config.
--}}

@section('title', $page->title . ' - ' . config('app.name'))
@section('description', $page->description)

@push('structured-data')
    <script type="application/ld+json">{!! \App\Support\StructuredData::forProduct() !!}</script>
    <script type="application/ld+json">{!! \App\Support\StructuredData::forFaq($page->faq(), $page->url(), $page->h1) !!}</script>
@endpush

@section('content')

    @php
        $yearlyPrice = \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Yearly);
        $monthlyPrice = \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Monthly);
        $yearlyPeriod = \App\Enums\Plan::Yearly->interval()->value;
        $monthlyPeriod = \App\Enums\Plan::Monthly->interval()->value;
        $saving = \App\Support\SubscriptionPrice::saving(\App\Enums\Plan::Yearly);
        $trialDays = config('subscription.trial_days');
        $graceDays = config('subscription.grace_days');
        $dynamicQuota = config('subscription.quotas.dynamic');
    @endphp

    {{-- 1. The H1 and the one sentence that answers the search. --}}
    <div class="eq-hero">
        <h1 class="eq-h1">{{ $page->h1 }}</h1>
        <p class="eq-lead">@yield('answer')</p>
    </div>

    {{-- 2. The free generator, the same one the homepage renders. --}}
    <x-static-generator :page="$page->slug" />

    <div class="eq-prose">

        {{-- 3. The problem, for this case. --}}
        @yield('problem')

        {{-- 4. Static against dynamic, for this case. --}}
        @yield('comparison')

        <div class="eq-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Static QR code</th>
                        <th>Dynamic QR code</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Change the link after printing</strong></td>
                        <td>No. The link is part of the pattern.</td>
                        <td>Yes, as often as you like.</td>
                    </tr>
                    <tr>
                        <td><strong>Scan tracking</strong></td>
                        <td>None. Scans never reach us.</td>
                        <td>Count, country, device and browser.</td>
                    </tr>
                    <tr>
                        <td><strong>Cost</strong></td>
                        <td>Free, for good.</td>
                        <td>{{ $yearlyPrice }} a {{ $yearlyPeriod }} or {{ $monthlyPrice }} a {{ $monthlyPeriod }}, after a {{ $trialDays }}-day free trial.</td>
                    </tr>
                    <tr>
                        <td><strong>Needs an account</strong></td>
                        <td>No.</td>
                        <td>Yes. The trial needs no card.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 5. How it works. --}}
        <h2>How it works</h2>
        @hasSection('steps')
            @yield('steps')
        @else
            <ol>
                <li><strong>Create.</strong> Make a dynamic code in your account and point it at your link.</li>
                <li><strong>Print.</strong> Download it as PNG or SVG and put it wherever it needs to go.</li>
                <li><strong>Update anytime.</strong> Change where it points from your account. The printed code stays the same.</li>
            </ol>
        @endif

        {{--
            6. What it costs. Yearly first, because it is the plan we recommend,
            with the saving stated only when there is one.
        --}}
        <h2>What it costs</h2>
        <p>
            Static codes are free, with no account. Dynamic codes cost
            <strong>{{ $yearlyPrice }} a {{ $yearlyPeriod }}</strong>@if ($saving !== null), which saves {{ $saving }} against paying monthly,@endif
            or {{ $monthlyPrice }} a {{ $monthlyPeriod }}. We recommend yearly. Tax is included. Every account starts with a
            {{ $trialDays }}-day free trial that needs no payment details, and a subscription covers
            {{ $dynamicQuota }} dynamic codes. Cancel anytime and keep access to the end of the period you
            paid for.
        </p>
        <p>
            If you stop paying, your dynamic codes stop resolving once the paid period and a further
            {{ $graceDays }} days of grace have passed. Nothing is deleted: the codes, their links and
            their scan history stay in your account, and every code starts working
            the moment you subscribe again. Static codes never depend on us. They keep working
            as long as the page they point to does. We never switch one off, but we cannot bring back a
            page that has gone.
        </p>
        <p><a href="{{ route('pricing') }}">See the full pricing</a></p>

        {{-- 7. The questions, each addressable and marked up above. --}}
        <h2>Questions</h2>
        @foreach ($page->faq() as $entry)
            <div class="eq-faq-item">
                <h3 id="{{ $entry['id'] }}" class="eq-faq-q">
                    <a href="#{{ $entry['id'] }}">{{ $entry['question'] }}</a>
                </h3>
                {!! $entry['answer'] !!}
            </div>
        @endforeach

    </div>

    {{-- 8. The call to action. --}}
    <div class="eq-result-panel">
        <div class="eq-result-info">
            <h2 class="eq-h2">Need to change the link later?</h2>
            @auth
                <p>Create a dynamic code in your dashboard and point it wherever you need.</p>
                <div class="eq-actions">
                    <a href="{{ route('filament.admin.resources.qr-codes.create') }}" class="eq-btn eq-btn-dark eq-btn--sm">Create a dynamic QR</a>
                </div>
            @else
                <p>{{ $trialDays }} days free, no card needed. Make a dynamic code and edit it whenever you like.</p>
                <div class="eq-actions">
                    <a href="{{ \App\Filament\Pages\Auth\Register::linkFrom(\App\Enums\SignupSource::LandingCta, $page->slug) }}" class="eq-btn eq-btn-dark eq-btn--sm">Start the free trial</a>
                </div>
            @endauth
        </div>
    </div>

    {{-- 9. Where to read next. Published pages only. --}}
    @if ($related !== [])
        <div class="eq-prose">
            <h2>Related</h2>
            <ul>
                @foreach ($related as $relatedPage)
                    <li><a href="{{ $relatedPage->url() }}">{{ $relatedPage->h1 }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

@endsection
