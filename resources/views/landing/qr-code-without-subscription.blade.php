@extends('layouts.landing')

{{--
    The page for someone who wants a code and not a bill. Static first,
    because it answers the search outright. Then what a subscription buys,
    and the part people miss: a dynamic code needs the subscription for as
    long as it is in print. Draft, unpublished until a person has read it.
--}}

@section('answer')
    You do not need a subscription to make a QR code here. The static generator above is free and asks
    for no account. A subscription only matters if you want to change the link after printing, or count
    the scans.
@endsection

@section('problem')
    <h2>A one-off code should not come with a monthly bill</h2>
    <p>
        Most people who search for this need one code, once. A sign-up form in a school newsletter, a page
        on a shop window, a review link on a receipt. Some make it, and weeks later find it was part of a
        trial that now wants a card.
    </p>
    <p>
        A code that opens one fixed link costs nothing to run, because the phone reads the link straight
        from the pattern. So there is nothing to subscribe to. Ours is free and stays free, and there is no
        account to cancel because there was never one to open.
    </p>

    <h2>What a subscription actually pays for</h2>
    <p>
        A dynamic code sends each scan through us first, so we can pass it on to whichever link you set
        today. That forwarding is the service. It is what lets you change the link on a printed poster, and
        see how often it is scanned and from where.
    </p>
    <p>
        Because the forwarding happens on every scan, the code needs the subscription for as long as it is
        in use. Subscribe for a month and cancel, and the code works to the end of that month and
        {{ config('subscription.grace_days') }} more days, then stops resolving. For a code that is only out
        for a few weeks, that is fine. For something that lasts years, budget for years, or print static.
    </p>
@endsection

@section('comparison')
    <h2>Keeping the cost down</h2>
    <p>
        Print <strong>static</strong> whenever the link will stay put. It is the cheapest code there is, and
        no subscription can be attached to it later.
    </p>
    <p>
        Print <strong>dynamic</strong> when getting the link wrong would cost more than the subscription.
        Add up what a reprint costs and how often the link is likely to change in a year. If that comes to
        more than {{ \App\Support\SubscriptionPrice::formatted(\App\Enums\Plan::Yearly) }}, dynamic is the
        cheaper choice. If not, print static and reprint when you must.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Start the trial, with no card asked for, and make a dynamic code.</li>
        <li><strong>Print.</strong> Download the SVG and put it on the piece that might need a new link later.</li>
        <li><strong>Update anytime.</strong> Change the link from your account while you subscribe, and cancel once the piece is no longer out there.</li>
    </ol>
@endsection
