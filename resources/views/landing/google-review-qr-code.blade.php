@extends('layouts.landing')

{{--
    The static-first page. A Google review link belongs to the Business
    Profile and rarely changes, so the honest answer is a free static code,
    and the page gives it before anything else. Dynamic is offered only for
    the reader who wants to count scans or reuse the print. Draft,
    unpublished until a person has read it.
--}}

@section('answer')
    Copy your Google review link, paste it into the generator below, and download a free static QR code.
    A review link rarely changes, so this code will usually work for as long as you print it.
@endsection

@section('problem')
    <h2>Happy customers rarely leave reviews</h2>
    <p>
        Most customers who enjoyed their visit mean to leave a review and forget by the time they get
        home. The ones who do write are usually the ones with a complaint. The fix is to ask while the
        customer is still there, and to make the review form one scan away rather than a search.
    </p>
    <p>
        A hairdresser who puts a small code by the till, on the appointment card and on the bottom of the
        receipt is asking three times without saying a word. A café can put it on the table next to the
        menu code. A plumber can put it on the invoice.
    </p>
    <p>
        The code only needs to open one thing: the review form for your business. That address is fixed,
        which makes this one of the few cases where the free code is the whole answer.
    </p>
@endsection

@section('comparison')
    <h2>You do not need a dynamic code for this</h2>
    <p>
        Your review link belongs to your Google Business Profile, and it does not change when you edit
        your opening hours or your photos. A static code holds that link in its pattern and opens it
        directly. It is free, it needs no account, and nothing we do can switch it off.
        Make it above, print it, and you are done.
    </p>
    <p>
        A dynamic code is worth it only if you want something a static code cannot give you. One reason
        is counting scans, to see whether the receipt or the till brings in more reviews. Another is
        reusing the same printed code for something else later, such as a feedback form of your own.
    </p>
    <p>
        If you go that way, the free part of the deal is gone. A dynamic code opens the review form only
        while you subscribe. When you stop, customers get a notice that it is not active, starting
        {{ config('subscription.grace_days') }} days after the last paid period.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Copy the review link from your Business Profile and paste it into the generator above.</li>
        <li><strong>Print.</strong> Download the SVG and put it by the till, on receipts and on cards. Add one line asking for a review.</li>
        <li><strong>Update anytime.</strong> With a static code there is nothing to update. If you chose dynamic, change the link from your account.</li>
    </ol>
@endsection
