@extends('layouts.landing')

{{--
    The use-case page for weddings and events. The invitation is printed
    months before the day, and plans change in between, so the code has to
    follow them. The page is honest that the code is wanted for months, not
    years, and says what happens once it is no longer paid for. Draft,
    unpublished until a person has read it.
--}}

@section('answer')
    Print a QR code on the invitation that opens your event page. Make it a dynamic code, so you can
    change the schedule, the venue or the link to the photos after the invitations go out.
@endsection

@section('problem')
    <h2>The invitations go out months before the plans settle</h2>
    <p>
        Wedding invitations are often posted four to six months ahead. In that time the ceremony moves
        from 2pm to 3pm, the hotel block fills and a second one is added, and the gift list moves to
        another shop. The printed card says none of this, and the code on it opens whatever page it was
        made for.
    </p>
    <p>
        If that page is one you can edit, such as a wedding website, the code keeps working. Often it is
        not. A form for replies gets replaced. A shared document gets a new link. On the day, the photo
        album is a link that did not exist when the cards were printed.
    </p>
    <p>
        Conferences, reunions and club events have the same problem. The programme is printed early, and
        the room changes the week before.
    </p>
@endsection

@section('comparison')
    <h2>Does an invitation need a dynamic code?</h2>
    <p>
        Not if it opens one page you control, and you will keep that page up for as long as anyone
        might scan the card. In that case, make a static code with the generator above. It is free, it
        needs no account, and there is no subscription to remember to cancel.
    </p>
    <p>
        If you want the code to open the reply form before the day, the schedule on the day and the
        photos after it, use a dynamic code. It is the same printed code throughout. You change where it
        goes from your account, and you can see how often it has been scanned.
    </p>
    <p>
        An event code is usually wanted for a few months. Once the day has passed, keep it pointing at the
        photos, or cancel. A guest who scans an old invitation once your last paid period and its
        {{ config('subscription.grace_days') }} days of grace are over will see a notice that the code is
        not active.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make a dynamic code and point it at your event page or your reply form.</li>
        <li><strong>Print.</strong> Put it on the invitation, the save-the-date and the welcome sign. It is one code for all of them.</li>
        <li><strong>Update anytime.</strong> Point it at the schedule on the day and the photo album afterwards.</li>
    </ol>
@endsection
