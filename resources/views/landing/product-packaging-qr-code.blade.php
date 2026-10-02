@extends('layouts.landing')

{{--
    The use-case page for packaging. A pack is printed in tens of thousands
    and stays in homes for years, which is longer than most web addresses
    last, so the destination must not be fixed. A product range runs into
    the Quota, which the page states with the way past it. Draft,
    unpublished until a person has read it.
--}}

@section('answer')
    A QR code on packaging should be a dynamic code. Then, when your website moves or the page for that
    product changes, you change the link and leave the boxes already printed as they are.
@endsection

@section('problem')
    <h2>Packaging lasts longer than web pages</h2>
    <p>
        A print run of packaging is often 20,000 units or more, and it sells for a year or two. Then it
        sits in a cupboard, a garage or a second-hand listing for years after that. Someone who wants the
        manual for a heater they bought three years ago will scan the code on the box.
    </p>
    <p>
        Web pages rarely last that long. A shop changes platform, a product page is renamed, or the brand
        is sold and its site folded into another one. Each of these breaks the addresses printed on every
        box still out there. Nobody on the team notices, because nobody scans their own old packaging.
    </p>
    <p>
        Reprinting is not an option for boxes that have already been sold. The code on them has to keep
        working with whatever you print next.
    </p>
@endsection

@section('comparison')
    <h2>Why packaging is a dynamic case</h2>
    <p>
        A dynamic code keeps the address on the box fixed and moves the page behind it. When the site is
        rebuilt, you change each code's destination in your account, and every box already printed opens
        the new page. Scan counts also show which products people scan most, and when.
    </p>
    <p>
        A static code from the generator above is free. It can work on packaging if it opens an address
        on your own domain that you are sure you will keep, and redirect when the page moves. It costs
        nothing, but it depends on someone remembering that address years from now.
    </p>
    <p>
        Count the products before you count the codes. {{ config('subscription.quotas.dynamic') }} dynamic
        codes come with a subscription, one each for a short range. A brand with a longer catalogue
        should ask us for more before the artwork is signed off.
    </p>
    <p>
        Be clear about the commitment, too. A box in someone's cupboard is still scanned years from now.
        Those scans only reach your page while the subscription runs. Once it ends, after
        {{ config('subscription.grace_days') }} days of grace, they reach a notice that the code is not
        active.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make one dynamic code per product page and point each at the page as it is today.</li>
        <li><strong>Print.</strong> Send the SVGs with the packaging artwork, and scan the printer's proof of each one.</li>
        <li><strong>Update anytime.</strong> When the site moves, change the destinations. Every box on every shelf follows.</li>
    </ol>
@endsection
