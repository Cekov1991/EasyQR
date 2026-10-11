{{--
    The promises a buyer is deciding on, said once at the front of the site
    rather than inside FAQ answers. Shared by the homepage and the pricing page,
    each half addressable: #guarantees and #scan-privacy.

    Every period is rendered from the config that enforces it. The refund window
    is fixed in the Refund Policy, so it is stated here as that policy states it.

    The lapse line is the one to read carefully before editing: a dynamic code
    stops resolving when the subscription ends. What we keep is the data, not
    the redirect. Saying otherwise would break the very promise this block is for.
--}}

@php
    $trialDays = config('subscription.trial_days');
    $graceDays = config('subscription.grace_days');
    $retentionMonths = config('site.scan_retention_months');
@endphp

<section class="eq-promises" aria-labelledby="guarantees">
    <h2 id="guarantees" class="eq-promises-title">What we promise before you pay</h2>
    <ul class="eq-promise-grid">
        <li class="eq-promise">
            <strong>No card for the trial.</strong>
            A {{ $trialDays }}-day free trial, with no payment details asked for. Nothing is charged when it ends.
        </li>
        <li class="eq-promise">
            <strong>Monthly or yearly, cancel anytime.</strong>
            No annual lock-in. Cancel from your account and keep access to the end of the period you paid for.
        </li>
        <li class="eq-promise">
            <strong>A refund if you change your mind.</strong>
            You have 14 days to ask for a full refund on your first payment.
            <a href="{{ url('/refund-policy') }}">Refund Policy</a>
        </li>
        <li class="eq-promise">
            <strong>A declined card does not darken your print.</strong>
            A failed renewal gets {{ $graceDays }} days of grace before anything changes.
        </li>
        <li class="eq-promise">
            <strong>Nothing deleted if you stop.</strong>
            Dynamic codes stop resolving, but the codes, their links and their scan history stay in your account,
            and they start again the moment you subscribe.
        </li>
        <li class="eq-promise">
            <strong>Free codes stay free.</strong>
            Static codes never depend on us, and we never turn one into a trial code.
        </li>
    </ul>

    <h2 id="scan-privacy" class="eq-promises-title">What a scan records</h2>
    <p class="eq-promises-text">
        For a dynamic code: the time, the approximate country, the device, system and browser the phone reports, and the referring page where there is one.
        <strong>No IP address</strong>, nothing tied to a name, and nothing that recognises the same person twice.
        Scan records are deleted after {{ $retentionMonths }} months. <strong>Static codes record nothing</strong>:
        we never learn the scan happened.
        <a href="{{ route('faq') }}#scan-data">More on scan data</a>
    </p>
</section>
