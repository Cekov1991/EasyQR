@extends('layouts.landing')

{{--
    The use-case page for menus. Its point is that the table tent is printed
    once and the menu is not, so it is honest that a menu kept at one fixed
    address needs nothing more than a free static code. Draft for the voice
    pilot, unpublished until a person has read it.
--}}

@section('answer')
    Print one QR code for your tables. Make it a dynamic code if the menu behind it will ever move to
    a new link, so you can change the menu without reprinting a single table tent.
@endsection

@section('problem')
    <h2>The menu changes. The table tents do not.</h2>
    <p>
        A typical case: you print forty table tents with a QR code that opens <em>summer-menu.pdf</em>. In
        September the autumn menu goes up as a new file, and every table keeps opening the summer one.
        Guests order dishes you no longer make, at last season's prices, and the staff spend the evening
        explaining.
    </p>
    <p>
        It is rarely the seasonal change alone. Prices go up, a supplier lets you down, you add a lunch
        menu, or you move from a PDF to an ordering page. Each change can mean a new link, and a static
        code cannot follow it.
    </p>
    <p>
        Reprinting forty tents is the obvious fix and the expensive one. It also means a week of the old
        tents on the tables while the new ones are made.
    </p>
@endsection

@section('comparison')
    <h2>Do you need a dynamic code for this?</h2>
    <p>
        Not always. If your menu lives at one address that never changes, a static code is all you need.
        That might be a page on your own website that you edit, or a file you always replace under the
        same name. Make it with the generator above, for free, and print it today.
    </p>
    <p>
        If the link itself is going to change, a dynamic code saves the reprint. The tents keep the same
        code; you paste the new link in your account and the next guest to scan gets the new menu. You
        also see how many people scan it, which tells you whether the tents are earning their place on
        the table.
    </p>
    <p>
        One thing to plan for: a code on a table has to work every night. If you stop paying, the code
        stops opening the menu {{ config('subscription.grace_days') }} days after your paid period ends.
        Guests see a plain notice, not a bill.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make a dynamic code in your account and point it at the menu you serve today.</li>
        <li><strong>Print.</strong> Put the same code on every table tent, the window and the takeaway bag.</li>
        <li><strong>Update anytime.</strong> When the menu changes, change the link. Nothing on the tables changes.</li>
    </ol>
@endsection
