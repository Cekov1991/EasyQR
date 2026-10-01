@extends('layouts.landing')

{{--
    The hub. A neutral explainer: it should leave someone able to choose
    correctly even if they choose static and never come back. Draft for the
    voice pilot, unpublished until a person has read it.
--}}

@section('answer')
    A static QR code is free and keeps working for good, but its link can never change. A dynamic QR
    code can be pointed somewhere new after it is printed, and it counts its scans.
@endsection

@section('problem')
    <h2>The choice that matters before you print</h2>
    <p>
        Say you print 500 flyers for a summer market. The QR code on them opens the stall list on your
        website. Two weeks later the site gets rebuilt and the stall list moves to a new address. If the
        code is static, all 500 flyers now open a page that is not there. The only fix is to print them
        again.
    </p>
    <p>
        If the code is dynamic, you log in, paste the new address, and every flyer already out there
        opens the right page on the next scan. Nothing about the printed code changes.
    </p>
    <p>
        That is the whole difference, and it is worth deciding before the print order goes in. Once a
        code is printed, you cannot turn one kind into the other.
    </p>
@endsection

@section('comparison')
    <h2>Which one fits</h2>
    <p>
        Print a <strong>static</strong> code when the link will not change while the code is out in the
        world. Your home page, a Google review page, a document that stays at one address. The generator
        at the top of this page makes one, and it is yours to keep.
    </p>
    <p>
        Print a <strong>dynamic</strong> code when the link might change, or when you want to know whether
        anyone is scanning. A menu whose dishes change, a sign that moves between properties, packaging
        that sits on shelves for years, a poster campaign you want to measure.
    </p>
    <p>
        Watch for one trap. Some generators hand you a dynamic code without saying so, then switch it off
        when a trial ends. The code was never free; it only looked that way until the flyers were printed.
        Our free codes are static, so there is nothing to switch off. We never turn them into trial codes.
    </p>
@endsection
