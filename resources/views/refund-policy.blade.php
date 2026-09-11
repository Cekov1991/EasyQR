@extends('layouts.site')

@section('title', 'Refund Policy - ' . config('app.name'))
@section('description', 'How refunds work for ' . config('app.name') . ' subscriptions.')

@section('content')

    <div class="eq-prose">
        <h1 class="eq-h1">Refund Policy</h1>
        <p class="eq-prose-updated">Last updated 11 September 2026</p>

        <h2>1. The free trial comes first</h2>
        <p>
            Every new account gets {{ config('subscription.trial_days') }} days of full access
            without entering any payment details. The trial exists so you can decide whether
            the service works for you before paying anything.
        </p>

        <h2>2. 14-day refund window</h2>
        <p>
            If you subscribe and change your mind, contact us within <strong>14 days</strong> of
            your first payment on a plan and we will refund it in full, no reason needed.
        </p>
        <p>
            A renewal is not a fresh purchase. It continues a subscription you have already had
            a full billing period to evaluate, and stopping it before it is charged takes two
            clicks from the Subscription page, so renewals sit outside this window.
        </p>
        <p>
            If you are a consumer in the EU or UK, this reflects your statutory right of
            withdrawal; nothing in this policy limits rights you have by law.
        </p>

        <h2>3. After 14 days</h2>
        <p>
            We do not refund the remainder of the period once the window has passed.
            You can cancel at any time to stop the next renewal, and you keep full access
            until the end of the period you have already paid for.
        </p>
        <p>
            If the service fails in a way we cannot resolve, for example your dynamic QR
            codes stop resolving through our fault, contact us and we will refund a fair
            share of the period affected.
        </p>

        <h2>4. Cancelling</h2>
        <p>
            Cancel from the <strong>Subscription</strong> page in your account. Cancelling stops
            future renewals; it is not itself a refund request. Your static QR codes remain free
            and keep working regardless.
        </p>

        <h2>5. How to request a refund</h2>
        <p>
            Email <a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a>
            from the address on your account, telling us which payment you mean. We aim to respond within
            two business days, and approved refunds return to the original payment method,
            typically within 5–10 business days depending on your bank.
        </p>
        <p>
            Payments are processed by AgentaOS as merchant of record, so the refund is issued
            through them and may appear on your statement under their name.
        </p>
    </div>

@endsection
