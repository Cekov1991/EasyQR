@extends('layouts.landing')

{{--
    The use-case page for agents. A sign outlives the listing it stands
    outside, so the code has to move with the sign, and an agent with several
    signs runs into the Quota, which the page states with the way past it.
    Draft, unpublished until a person has read it.
--}}

@section('answer')
    Put a dynamic QR code on each sign and point it at the listing the sign is standing outside. When
    the sign moves to the next property, change the link. The sign itself never needs reprinting.
@endsection

@section('problem')
    <h2>The sign moves. The listing behind it should too.</h2>
    <p>
        A good sign costs real money, so it goes up outside one house after another. The code on it is
        the part that goes stale. Print a code for <em>12 Oak Lane</em>, sell the house, and move the
        sign to Birch Road. Every buyer who scans it there now sees a sold house three streets away.
    </p>
    <p>
        Most agents fix this with stickers, or they leave the code off the sign. Both waste the best
        moment you get: someone standing outside the property, phone in hand, wanting the price and the
        photos now.
    </p>
    <p>
        Flyers in the window box and open-house boards have the same problem on a shorter clock. They
        are printed for one property, but the page they open often changes before they are thrown away.
        The price drops, the open-house time moves, or the listing goes under offer.
    </p>
@endsection

@section('comparison')
    <h2>Static or dynamic for a sign?</h2>
    <p>
        A static code is right for anything printed for one property and thrown away with it, as long as
        its page stays up. A one-off brochure is a good example. Make it with the generator above, for
        free.
    </p>
    <p>
        A sign needs a dynamic code. The pattern on the sign never changes. You change the listing it
        opens from your account, and each sign counts its own scans, so you can see which streets get
        attention.
    </p>
    <p>
        Count your signs before you start. Your subscription includes
        {{ config('subscription.quotas.dynamic') }} dynamic codes, enough for one per sign in a small
        office. A busier office can email us for a higher limit, as the questions further down explain.
    </p>
    <p>
        If you leave the subscription, every sign goes quiet together:
        {{ config('subscription.grace_days') }} days after the last paid period, a buyer who scans one gets
        a short notice that the code is not active, and no listing.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make one dynamic code per sign, named after the sign, and point it at the current listing.</li>
        <li><strong>Print.</strong> Send the SVG to your sign maker. Make the code large enough to scan from the pavement.</li>
        <li><strong>Update anytime.</strong> When the sign moves, paste in the next listing before it goes up.</li>
    </ol>
@endsection
