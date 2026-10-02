@extends('layouts.landing')

{{--
    The use-case page for flyers and posters. Its angle is measurement: a
    code per batch shows which placement is being scanned, and a campaign
    with many placements runs into the Quota, which the page states with the
    way past it. Draft, unpublished until a person has read it.
--}}

@section('answer')
    Give each batch of flyers or posters its own dynamic QR code. You can then see which batch people
    scan, and change where the codes point while the campaign is still running.
@endsection

@section('problem')
    <h2>You printed 3,000 flyers. Which ones worked?</h2>
    <p>
        A gym prints 3,000 flyers for a January offer. A thousand go through doors, a thousand sit on the
        counters of nearby cafés, and a thousand are handed out at the station. Twelve people sign up.
        Nobody can say which thousand brought them in, so next year the gym prints the same mix again.
    </p>
    <p>
        Posters raise a second problem. The offer ends on the 31st, but the posters stay up into March.
        Anyone who scans one then lands on a page about an offer that has ended, or a page that has
        gone.
    </p>
    <p>
        A code that opens the same page from every flyer, and opens it for good, cannot help with either
        problem.
    </p>
@endsection

@section('comparison')
    <h2>One code, or one per batch?</h2>
    <p>
        For a single run that opens a page you will keep, a static code is enough. A flyer for a shop
        that links to its home page is an example. Make it with the generator above, for free. It works
        as well on paper as any other code. It just tells you nothing about who scanned it.
    </p>
    <p>
        To compare batches, print a different dynamic code on each one. They can all open the same page.
        Each code keeps its own count of scans, so the station batch and the café batch can be compared
        within a week. When the offer ends, point every code at the next one, and the posters still on
        walls keep working for you.
    </p>
    <p>
        Batches add up quickly, and the number of codes is capped: {{ config('subscription.quotas.dynamic') }}
        dynamic codes per subscription. Planning more placements than that? Write to us for a higher cap while the
        artwork is still with you, not once the printer is waiting.
    </p>
    <p>
        A campaign that has ended can still be paying for itself through the posters left up. Once you
        cancel and {{ config('subscription.grace_days') }} days have passed after the final paid period, a
        scan of one of those posters opens a notice that the code is not active.
    </p>
@endsection

@section('steps')
    <ol>
        <li><strong>Create.</strong> Make one dynamic code per batch and name it after the place it goes, such as "station" or "cafés".</li>
        <li><strong>Print.</strong> Put each code on its own batch, and scan one copy of each before they go out.</li>
        <li><strong>Update anytime.</strong> Compare the scans, and move every code to the next offer when this one ends.</li>
    </ol>
@endsection
