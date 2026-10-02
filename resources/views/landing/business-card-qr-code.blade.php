@extends('layouts.landing')

{{--
    The use-case page for business cards. Cards are printed in boxes of
    several hundred and outlast the job, the link or the profile on them, so
    the page is honest that a static code suits a card whose link is safe and
    a dynamic one suits the rest. Draft, unpublished until a person has read
    it.
--}}

@section('answer')
    Put a QR code on your business card that opens a page about you. Make it a dynamic code if that
    page could change before you run out of cards, so you can update the link instead of reprinting.
@endsection

@section('problem')
    <h2>Five hundred cards, one link</h2>
    <p>
        Business cards come in boxes of 250 or 500, and most people use a few a month. A box can last two
        years. In that time, plenty can change: your job title or your employer, the booking page you
        use, or a portfolio you move to a new site.
    </p>
    <p>
        The printed name and number can be crossed out in pen. The code cannot. Someone keeps your card,
        finds it next spring, scans it, and lands on a profile you closed or a team page that no longer
        lists you. You never hear about it. They just do not get in touch.
    </p>
    <p>
        A wrong code is worse than none, because it looks like the right one. Nobody checks the link
        before they scan.
    </p>
@endsection

@section('comparison')
    <h2>Which kind belongs on a card?</h2>
    <p>
        If the code opens a page you own and will keep, a static code is fine. Your own domain, with a
        page you can edit, is a good example. Make it with the generator above, for free, and you never
        pay for it.
    </p>
    <p>
        If the page belongs to someone else, a dynamic code is the safer choice. That covers an
        employer's site, a booking service and a social profile. When you move on, you change one link in
        your account, and every card already in someone's wallet follows. You also see when cards get
        scanned, which shows whether the last conference was worth the ticket.
    </p>
    <p>
        Weigh one thing before you choose. Cards outlive subscriptions. If yours ends while cards are still
        being handed out, the code shows a notice that it is not active from
        {{ config('subscription.grace_days') }} days after the last paid period. If you can't see yourself
        paying for as long as the box lasts, put a static code on the card and point it at a page you
        will keep.
    </p>
    <p>
        Keep the code small but not tiny. Two centimetres across is about the least that scans reliably
        from a card held in the hand, and the white margin around it counts.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make a dynamic code and point it at the page you want people to find.</li>
        <li><strong>Print.</strong> Send the SVG to your printer with the card artwork, and scan the proof.</li>
        <li><strong>Update anytime.</strong> When the job or the link changes, change the destination. The cards stay as they are.</li>
    </ol>
@endsection
