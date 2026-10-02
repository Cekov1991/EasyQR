@extends('layouts.landing')

{{--
    The problem page for a code a service now says is over. It shares its
    search with the stopped-working page, so it takes the other half of the
    job: that page explains why codes die, this one assumes the code is dead
    and walks the fix, from whose code it is to what goes on the replacement.
    "Expired" is the searcher's word, so it is in the title and the H1 and
    nowhere in the copy. Draft, unpublished until a person has read it.
--}}

@section('answer')
    A QR code that opens a notice instead of your page is usually a dynamic code whose trial or
    subscription ended. The printed pattern has not worn out. The fix is in the account behind it, or
    in a new code.
@endsection

@section('problem')
    <h2>First, work out whose code it is</h2>
    <p>
        Scan the code and look at the address bar before you close the notice. The page belongs to the
        company that made the code. Its name is usually in the address, and often on the notice too.
    </p>
    <p>
        <strong>You made it.</strong> Log in to that company with the email you used and find the code.
        Check when the trial or plan ended. Many people find they signed up for a free trial months ago
        and never noticed the code depended on it.
    </p>
    <p>
        <strong>Someone else made it.</strong> Perhaps the previous owner of the shop, or an agency you no
        longer work with. You cannot reach the account, so ask them for the login. If that goes nowhere,
        treat the code as gone and replace it.
    </p>
    <p>
        <strong>The notice is ours.</strong> Then the code is one of ours and the account has lapsed. Log
        in and subscribe, and the code opens the same page as before, straight away.
    </p>

    <h2>Then pick the fix that costs least</h2>
    <p>
        <strong>Pay to restore it.</strong> If the code is on thousands of labels, or on a sign you cannot
        easily replace, paying may be the cheapest way out. Ask first what it will cost each year from now
        on, because the code will depend on that payment for as long as it is printed.
    </p>
    <p>
        <strong>Cover it.</strong> For a handful of pieces, such as a door, a counter display or a few table
        tents, a sticker with a new code over the old one takes an afternoon.
    </p>
    <p>
        <strong>Reprint.</strong> For anything due a reprint soon, put the new code on the next run and let
        the old pieces run out.
    </p>
@endsection

@section('comparison')
    <h2>What to put on the replacement</h2>
    <p>
        If the page it opens will stay where it is, use a <strong>static</strong> code from the generator
        above. No account, no trial, nothing for anyone to switch off. It opens your page for as long as
        that page is there.
    </p>
    <p>
        If you may need to change the page later, a <strong>dynamic</strong> code still makes sense, as
        long as you choose it knowing the terms. Ours works while you subscribe, and for
        {{ config('subscription.grace_days') }} days after your paid period ends. Then it shows a plain
        notice, much like the one you just met. We would rather you knew that before you print.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make the replacement code and point it at the page people should land on today.</li>
        <li><strong>Print.</strong> If it goes on as a sticker, print it larger than the old code so it covers the pattern and its margin.</li>
        <li><strong>Update anytime.</strong> With a dynamic code, change the link from your account when the page moves. Nothing printed changes.</li>
    </ol>
@endsection
