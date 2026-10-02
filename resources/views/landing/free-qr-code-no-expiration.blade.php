@extends('layouts.landing')

{{--
    The page for someone who has been caught by a trial code, or wants to be
    sure they will not be. It is mostly about static, says why static can be
    free without a catch, and is plain about the two things static cannot do.
    Draft, unpublished until a person has read it.
--}}

@section('answer')
    The code you make at the top of this page is free and has no end date. It is a static QR code: your
    link is drawn into the pattern, no account is involved, and there is nothing for anyone to switch off.
@endsection

@section('problem')
    <h2>Why some free codes stop and this one does not</h2>
    <p>
        A QR code is a link drawn in squares. A static code draws your own link. Scan it and the phone goes
        straight to your page, without asking anyone. No server sits in between, so there is nothing to
        bill for and nothing that can run out.
    </p>
    <p>
        Some generators draw a different link: a short one on their own domain that forwards to yours.
        That is a dynamic code. Forwarding is useful, but it is a service, and a service can stop. When the
        code was only free for a trial, it stops when the trial does, often after it is already printed on
        a menu or the side of a van.
    </p>
    <p>
        Our free codes are always static. We never hand out a dynamic code dressed up as a free one, and we
        never turn a static code into a trial code later. We could not, even if we wanted to.
    </p>

    <h2>What no catch means here</h2>
    <p>
        No account and no email address. No trial, and no date when anything changes. The code is plain
        black on white with nothing of ours added, and you download it as PNG or SVG. We keep no copy of
        the code or its link, so download it before you leave the page.
    </p>
    <p>
        The one thing that can stop a static code is the page it opens. If that page is moved or taken
        down, the code opens nothing, and no generator can fix that. Point it at an address you control and
        expect to keep.
    </p>
@endsection

@section('comparison')
    <h2>When free is not enough</h2>
    <p>
        A static code cannot change. Once printed, it opens the same link for good, and it cannot tell you
        whether anyone scanned it. For a review page, a home page or a document that stays put, that is
        exactly right.
    </p>
    <p>
        If the link might change while the code is in print, you need a <strong>dynamic</strong> code, and
        that one is not free for good. Ours starts with a trial that needs no card and charges nothing when
        it ends. After that it is a subscription. If the subscription stops, the code stops resolving
        {{ config('subscription.grace_days') }} days after the paid period ends. That is the honest price of
        a code you can edit.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Paste your link into the generator at the top of the page. For a code you can edit, make a dynamic one in your account instead.</li>
        <li><strong>Print.</strong> Use the SVG for print and the PNG for screens, and scan the proof before the run goes ahead.</li>
        <li><strong>Update anytime.</strong> A dynamic code can be pointed somewhere new from your account. A static one is changed by printing a new code.</li>
    </ol>
@endsection
