@extends('layouts.landing')

{{--
    The problem page for a code that has already failed. It diagnoses first,
    because half of these are fixable without reprinting anything, and only
    then says how to print one that will not do it again. It pitches dynamic,
    so it says plainly that a dynamic code of ours also stops when an account
    lapses. Draft for the voice pilot, unpublished until a person has read it.
--}}

@section('answer')
    A printed QR code stops working for one of three reasons: the page it opens has gone, the service
    behind it switched it off, or the print itself cannot be read. Which one it is decides the fix.
@endsection

@section('problem')
    <h2>Find out which kind of code you have</h2>
    <p>
        Scan it and read the link your phone shows before you tap it. That link tells you most of what
        you need.
    </p>
    <p>
        <strong>The link is your own website.</strong> The code is static: the address is printed into
        the pattern itself, and no service sits in between. The code is fine. The page is not. Maybe the
        site was rebuilt, or a file was renamed. You can usually fix this without reprinting: put the
        page back at that address, or ask whoever runs your site to redirect the old address to the new
        one.
    </p>
    <p>
        <strong>The link is on a QR code company's domain.</strong> The code is dynamic. Every scan goes
        through that company first, and it has stopped sending people on. Often this is a free trial that
        ended, on a code that was never described as a trial code. Only that company can turn it back
        on, usually by taking payment. If you would rather not pay them, the code has to be reprinted.
    </p>
    <p>
        <strong>The phone shows no link at all.</strong> Then the print is the problem: too small, cropped
        at the edge, light on dark, or on a glossy surface that catches the light. Try another phone first.
    </p>
@endsection

@section('comparison')
    <h2>How to make sure the next one keeps working</h2>
    <p>
        If the link will stay put, print a <strong>static</strong> code. The generator at the top of this
        page makes one without an account. Scans never touch us, so we cannot switch it off, and nobody
        else can either. It works for as long as the page it opens is there.
    </p>
    <p>
        If the link might move, print a <strong>dynamic</strong> code, so the next move is a change in
        your account instead of a reprint. Know what you are signing up for, though, because a dynamic
        code from us depends on us too. If you stop paying, it stops resolving
        {{ config('subscription.grace_days') }} days after your paid period ends, the same way the code you
        are holding did. The difference is that we tell you now, before you print.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make a dynamic code and point it at the page that works today.</li>
        <li><strong>Print.</strong> Download the SVG and print it at a size that scans from where people will stand.</li>
        <li><strong>Update anytime.</strong> Next time the page moves, change the link in your account. The code you printed carries on.</li>
    </ol>
@endsection
