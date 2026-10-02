@extends('layouts.landing')

{{--
    The page for a code already printed and pointing at the wrong place. It
    is honest that a static code cannot be edited, gives the fixes that work
    anyway, and moves the reader to dynamic on the next print run rather than
    pretending the current one can be saved. Draft, unpublished until a
    person has read it.
--}}

@section('answer')
    You can change where a printed QR code goes only if it is a dynamic code: you edit the link in the
    account that made it. A static code cannot be changed, but the page it opens can often be redirected.
@endsection

@section('problem')
    <h2>Check which kind you printed</h2>
    <p>
        Scan the code and read the link before it opens. If it is a short link on a QR code company's
        domain, the code is dynamic. Log in to that account and change the destination. Every copy you
        printed follows on the next scan.
    </p>
    <p>
        If the link is your own address, the code is static, and the pattern cannot be edited. Say 2,000
        leaflets point at <em>yourshop.com/spring-offer</em>, and the offer has ended. Those leaflets will
        open that address for as long as they exist.
    </p>

    <h2>What you can do with a static code already in print</h2>
    <p>
        <strong>Change what is at the address.</strong> If the domain is yours, have the old address
        redirect to the new page. Every leaflet now lands in the right place and nothing is reprinted. Try
        this first.
    </p>
    <p>
        <strong>Cover it.</strong> If the address is not yours, such as a file-sharing link or a profile you
        have since closed, put a sticker with a new code over the old one on anything still in your hands.
    </p>
    <p>
        <strong>Let the run end.</strong> If neither works, let the old pieces run out and fix the next
        print run.
    </p>
@endsection

@section('comparison')
    <h2>Make the next print run editable</h2>
    <p>
        On the next run, print a <strong>dynamic</strong> code. The pattern holds a short link that never
        changes, and your account decides where it sends people. The next time a page moves, you change
        one field instead of placing a print order.
    </p>
    <p>
        Keep the old address working while old copies are still around. They stay static, and a dynamic
        code on the new run does nothing for them.
    </p>
    <p>
        A dynamic code from us works while you subscribe. If you stop, it stops resolving
        {{ config('subscription.grace_days') }} days after your paid period ends, and the new run opens a
        plain notice instead of your page. If the next run points at a page you are sure will never move, a
        free <strong>static</strong> code from the generator above is still the right choice.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make a dynamic code and point it at the page the next run should open.</li>
        <li><strong>Print.</strong> Send the SVG to the printer with the next run, and scan the proof before it goes ahead.</li>
        <li><strong>Update anytime.</strong> When that page moves, change the link in your account. The run you printed keeps working.</li>
    </ol>
@endsection
