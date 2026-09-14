@extends('layouts.site')

@section('title', 'FAQ - ' . config('app.name'))
@section('description', 'Answers to the questions people ask before printing a QR code: static versus dynamic, editing a code after it is printed, what the subscription costs, and what happens when it lapses.')

{{--
    The product markup as well as the questions. This page quotes both prices
    and describes what the subscription buys, so it is one of the pages that
    sells the product, not merely one that mentions it.
--}}
@push('structured-data')
    <script type="application/ld+json">{!! \App\Support\StructuredData::forProduct() !!}</script>
    <script type="application/ld+json">{!! \App\Support\StructuredData::forFaq() !!}</script>
@endpush

@section('content')

    <div class="eq-hero">
        <h1 class="eq-h1">Frequently asked questions</h1>
        <p class="eq-lead">
            What a QR code can and cannot do once it is printed, what the subscription
            costs, and what happens if you stop paying.
        </p>
    </div>

    <div class="eq-prose">

        {{--
            Answers are written in App\Support\Faq and rendered raw, because
            several of them link to the policy that governs them. The same
            source feeds the FAQPage markup above, so the answer a search engine
            quotes is the answer on the page.
        --}}
        @foreach (\App\Support\Faq::sections() as $section)
            <section class="eq-faq-section">
                <h2>{{ $section['title'] }}</h2>

                @foreach ($section['questions'] as $entry)
                    <div class="eq-faq-item">
                        {{-- Addressable, so a support reply can link to one answer. --}}
                        <h3 id="{{ $entry['id'] }}" class="eq-faq-q">
                            <a href="#{{ $entry['id'] }}">{{ $entry['question'] }}</a>
                        </h3>
                        {!! $entry['answer'] !!}
                    </div>
                @endforeach
            </section>
        @endforeach

        <section class="eq-faq-section">
            <h2>Still stuck?</h2>
            <p>
                Email <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a>
                and a person will answer. If you found a QR code on this domain that leads
                somewhere harmful, <a href="{{ route('report.create') }}">report it</a> instead —
                that one needs no account and goes straight to us.
            </p>
            <p class="eq-prose-updated">
                The full detail lives in our <a href="{{ route('pricing') }}">Pricing</a>,
                <a href="{{ url('/terms-and-conditions') }}">Terms and Conditions</a>,
                <a href="{{ url('/privacy-policy') }}">Privacy Policy</a> and
                <a href="{{ url('/refund-policy') }}">Refund Policy</a>. Where this page and one of
                those disagree, the policy is what counts.
            </p>
        </section>

    </div>

@endsection
