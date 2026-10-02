<?php

namespace App\Support;

use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

/**
 * Every Landing Page, published or not, and the one place that decides which
 * of them exist as far as a visitor or a crawler can tell.
 *
 * A page that is not published has no route, no sitemap entry, no llms.txt
 * line and no link pointing at it from another page, so unpublishing is one
 * flag and cannot leave a 404 linked from anywhere. The routes, the crawler
 * files, the footer and the related links all read `published()` for that
 * reason rather than keeping their own lists.
 *
 * A page is published by a person, after reading its copy: the flag here is
 * the only switch. `site.landing_pages.publish` exists so a test can render a
 * page still in review without that switch being thrown.
 */
class LandingPages
{
    /**
     * The neutral explainer every other page links back to.
     */
    public const HUB = 'static-vs-dynamic-qr-code';

    /**
     * The page label for the homepage, which is not a Landing Page but embeds
     * the same generator.
     */
    public const HOME = 'home';

    /**
     * Every Landing Page, keyed by slug, in the order the spec lists them.
     *
     * Titles are written without the site name, which the layout appends, and
     * must leave room for it inside the sixty characters a search result shows.
     *
     * @return array<string, LandingPage>
     */
    public static function all(): array
    {
        $pages = [
            new LandingPage(
                slug: self::HUB,
                title: 'Static vs dynamic QR code: what to print',
                description: 'A static QR code is free and works for good, but it can never change. A dynamic one can be edited after printing. Here is how to choose.',
                h1: 'Static vs dynamic QR code: which one should you print?',
                keyword: 'static vs dynamic qr code',
                related: [],
                faq: [
                    self::whichToPrint(),
                    'static-vs-dynamic',
                    'edit-after-printing',
                    'static-expiry',
                    'stop-paying',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'qr-code-stopped-working',
                title: 'QR code stopped working? Why, and the fix',
                description: 'Why a printed QR code stops working, how to tell which kind you have, and how to make sure the next one keeps working.',
                h1: 'QR code stopped working? Here is why',
                keyword: 'qr code stopped working',
                related: ['qr-code-expired', 'fix-printed-qr-code', 'free-qr-code-no-expiration'],
                faq: [
                    self::canItStopOnItsOwn(),
                    self::askedToUpgrade(),
                    'static-expiry',
                    'stop-paying',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'qr-code-expired',
                title: 'QR code expired? What happened and the fix',
                description: 'Your QR code says it has expired. What that usually means, whether it can be brought back, and how to print one that will not do it again.',
                h1: 'QR code expired? What happened, and how to fix it',
                keyword: 'qr code expired',
                related: ['qr-code-stopped-working', 'fix-printed-qr-code', 'free-qr-code-no-expiration'],
                faq: [
                    self::switchBackOn(),
                    self::stickerOverOldCode(),
                    'static-expiry',
                    'free-trial',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'free-qr-code-no-expiration',
                title: 'Free QR code with no expiration',
                description: 'Make a static QR code that is free for good: no account, no trial, no date it stops. Generated on the page and downloaded straight away.',
                h1: 'A free QR code with no expiration, and no catch',
                keyword: 'free qr code no expiration',
                related: ['qr-code-without-subscription', 'qr-code-expired', 'google-review-qr-code'],
                faq: [
                    self::whyStaticIsFree(),
                    self::tellStaticFromDynamic(),
                    'static-expiry',
                    'static-recover',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'qr-code-without-subscription',
                title: 'QR code generator with no subscription',
                description: 'Static QR codes need no subscription at all. If you need to edit a code after printing, here is what that costs and how to cancel.',
                h1: 'A QR code generator with no subscription',
                keyword: 'qr code generator no subscription',
                related: ['free-qr-code-no-expiration', 'qr-code-stopped-working', 'fix-printed-qr-code'],
                faq: [
                    self::payOnce(),
                    self::subscribeForOneMonth(),
                    'free-trial',
                    'cancelling',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'fix-printed-qr-code',
                title: 'Change a QR code after printing',
                description: 'Whether the QR code you already printed can be changed, what to do if it cannot, and how to make the next print run editable.',
                h1: 'How to change a QR code after printing',
                keyword: 'change qr code after printing',
                related: ['qr-code-stopped-working', 'product-packaging-qr-code', 'restaurant-menu-qr-code'],
                faq: [
                    self::redirectTheOldAddress(),
                    self::dynamicMadeElsewhere(),
                    'edit-after-printing',
                    'static-to-dynamic',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'restaurant-menu-qr-code',
                title: 'QR code for a restaurant menu',
                description: 'Put one QR code on every table and change the menu behind it whenever the dishes or prices change, without reprinting a single table tent.',
                h1: 'QR code for restaurant menus: print once, change the menu',
                keyword: 'qr code for restaurant menu',
                related: ['flyer-poster-qr-code', 'event-qr-code', 'fix-printed-qr-code'],
                faq: [
                    self::oneCodePerTable(),
                    self::menuAsPdf(),
                    'edit-after-printing',
                    'printing',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'real-estate-qr-code',
                title: 'QR code for a real estate sign',
                description: 'Print one QR code on your sign and point it at whichever listing the sign is standing outside, then move it to the next property.',
                h1: 'A QR code for your real estate sign',
                keyword: 'real estate sign qr code',
                related: ['flyer-poster-qr-code', 'business-card-qr-code', 'fix-printed-qr-code'],
                faq: [
                    self::signFollowsTheProperty(),
                    self::whichSignWasScanned(),
                    'more-codes',
                    'printing',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'business-card-qr-code',
                title: 'QR code for a business card',
                description: 'A QR code on your business card that you can point somewhere new when your job or your link changes, long before the cards run out.',
                h1: 'A QR code for your business card',
                keyword: 'qr code for business card',
                related: ['real-estate-qr-code', 'event-qr-code', 'flyer-poster-qr-code'],
                faq: [
                    self::whatTheCardShouldOpen(),
                    self::changingJobs(),
                    'edit-after-printing',
                    'logo-and-colour',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'event-qr-code',
                title: 'QR code for an event or wedding',
                description: 'Put a QR code on the invitation and update the schedule, the venue or the photo album behind it after the invitations are printed.',
                h1: 'A QR code for your event or wedding',
                keyword: 'qr code for event',
                related: ['flyer-poster-qr-code', 'business-card-qr-code', 'restaurant-menu-qr-code'],
                faq: [
                    self::subscribeForOneEvent(),
                    self::afterTheEvent(),
                    'free-trial',
                    'cancelling',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'flyer-poster-qr-code',
                title: 'QR code for a flyer or poster',
                description: 'A QR code for each batch of flyers or posters, so you can see which batch is being scanned and change where it points mid-campaign.',
                h1: 'A QR code for your flyer or poster',
                keyword: 'qr code for flyer',
                related: ['event-qr-code', 'real-estate-qr-code', 'product-packaging-qr-code'],
                faq: [
                    self::codePerBatch(),
                    self::whenTheCampaignEnds(),
                    'scan-data',
                    'more-codes',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'product-packaging-qr-code',
                title: 'QR code on product packaging',
                description: 'Packaging stays on shelves and in homes for years. Put a QR code on it whose destination you can still change when your site moves.',
                h1: 'A QR code on your product packaging',
                keyword: 'qr code on packaging',
                related: ['fix-printed-qr-code', 'flyer-poster-qr-code', 'restaurant-menu-qr-code'],
                faq: [
                    self::codePerProduct(),
                    'more-codes',
                    self::staticOnPackaging(),
                    'static-to-dynamic',
                ],
                published: false,
            ),
            new LandingPage(
                slug: 'google-review-qr-code',
                title: 'QR code for Google reviews',
                description: 'Make a free QR code that opens your Google review page, print it at the till or on the receipt, and let customers review you in one scan.',
                h1: 'A QR code for Google reviews',
                keyword: 'qr code for google reviews',
                related: ['free-qr-code-no-expiration', 'restaurant-menu-qr-code', 'business-card-qr-code'],
                faq: [
                    self::findTheReviewLink(),
                    self::reviewLinkAfterAMove(),
                    self::askingForReviews(),
                    'printing',
                ],
                published: false,
            ),
        ];

        $forced = (array) config('site.landing_pages.publish', []);

        return collect($pages)
            ->map(fn (LandingPage $page): LandingPage => in_array($page->slug, $forced, true) ? $page->asPublished() : $page)
            ->keyBy('slug')
            ->all();
    }

    /**
     * The pages a visitor or a crawler can reach.
     *
     * @return array<string, LandingPage>
     */
    public static function published(): array
    {
        return array_filter(self::all(), fn (LandingPage $page): bool => $page->published);
    }

    /**
     * The labels a counted event may carry to say which page it happened on:
     * the homepage, or a page a visitor can reach. A page still in review
     * cannot collect counts, and nothing outside this list is ever stored.
     *
     * @return array<int, string>
     */
    public static function pageLabels(): array
    {
        return [self::HOME, ...array_keys(self::published())];
    }

    /**
     * The label for a request that sent none: the homepage, the only page a
     * script cached from before the label existed could have come from.
     */
    public static function pageLabelOrHome(?string $label): string
    {
        return $label ?? self::HOME;
    }

    public static function find(string $slug): ?LandingPage
    {
        return self::all()[$slug] ?? null;
    }

    public static function isPublished(string $slug): bool
    {
        return self::find($slug)?->published ?? false;
    }

    /**
     * Resolve a slug that arrived on a URL to a page a visitor can reach, or
     * null. The homepage label is not a Landing Page, and neither is a page
     * still in review: nobody could have clicked a link on it.
     */
    public static function publishedSlug(?string $slug): ?string
    {
        return $slug !== null && self::isPublished($slug) ? $slug : null;
    }

    /**
     * Where a page sends a reader next. The hub links to every published page,
     * as the map of the rest. Any other page links up to three of its own
     * siblings and then the hub. Unpublished pages are skipped, so a link here
     * never lands on a 404.
     *
     * @return array<int, LandingPage>
     */
    public static function relatedTo(LandingPage $page): array
    {
        $published = self::published();

        if ($page->isHub()) {
            return array_values(array_filter($published, fn (LandingPage $other): bool => ! $other->isHub()));
        }

        $siblings = collect($page->related)
            ->map(fn (string $slug): ?LandingPage => $published[$slug] ?? null)
            ->filter()
            ->take(3)
            ->values()
            ->all();

        return isset($published[self::HUB]) ? [...$siblings, $published[self::HUB]] : $siblings;
    }

    /**
     * One named route per published page, at the top level of the site.
     *
     * Registered last in routes/web.php, and only for pages that are
     * published, so an unpublished slug is a plain 404 and no existing route
     * can be shadowed by one.
     */
    public static function registerRoutes(): void
    {
        foreach (self::published() as $page) {
            Route::get($page->slug, LandingPageController::class)
                ->defaults('slug', $page->slug)
                ->name($page->routeName());
        }
    }

    /**
     * The question the hub exists to answer, asked the way someone with a
     * print order open asks it.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function whichToPrint(): array
    {
        return [
            'id' => 'which-to-print',
            'question' => 'Should I print a static or a dynamic QR code?',
            'answer' => <<<'HTML'
                <p>
                    Ask whether the link could change while the code is still out in the world. If it
                    never will — your home page, a review page, a document that stays put — print a
                    static code. It is free and needs nothing from us.
                </p>
                <p>
                    If it might — a menu, a listing, an event schedule, a campaign that moves — print a
                    dynamic code. A static code can only be corrected by printing a new one.
                </p>
                HTML,
        ];
    }

    /**
     * What someone holding a dead code suspects first: that codes wear out.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function canItStopOnItsOwn(): array
    {
        return [
            'id' => 'stop-on-its-own',
            'question' => 'Can a QR code stop working on its own?',
            'answer' => <<<'HTML'
                <p>
                    No. The pattern is just a link drawn in squares, and it does not wear out or run down.
                    When a code that used to work stops, something behind it changed: the page it opens
                    was moved or taken down, or the service it runs through stopped sending scans on. The
                    other cause is physical, such as a faded, scratched or badly reprinted code.
                </p>
                HTML,
        ];
    }

    /**
     * What the trap looks like from the phone of whoever scanned the code.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function askedToUpgrade(): array
    {
        return [
            'id' => 'asked-to-upgrade',
            'question' => 'Why does my QR code open a page asking me to upgrade?',
            'answer' => <<<'HTML'
                <p>
                    Because it is a dynamic code and nobody is paying for the account it belongs to any
                    more, most often after a free trial ran out. The person scanning cannot fix that.
                    Whoever made the code can, by paying that service or by printing a new code. Our
                    own inactive codes show a plain notice instead, and never ask a stranger to pay.
                </p>
                HTML,
        ];
    }

    /**
     * The first hope of someone holding a code a service has switched off:
     * that paying will bring the same print back.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function switchBackOn(): array
    {
        return [
            'id' => 'switch-back-on',
            'question' => 'Can the old code be switched back on?',
            'answer' => <<<'HTML'
                <p>
                    Usually, but only by the company the code runs through. The pattern holds a short link
                    on their domain, so they decide whether scans are sent on. Most will turn it back on
                    once the account is paid for. Before you pay, ask them to confirm that the same
                    printed code will open your page again. No other generator can take it over.
                </p>
                HTML,
        ];
    }

    /**
     * The quick fix for a handful of printed pieces, and the way it goes
     * wrong.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function stickerOverOldCode(): array
    {
        return [
            'id' => 'sticker-over-old-code',
            'question' => 'Can I stick a new code over the old one?',
            'answer' => <<<'HTML'
                <p>
                    Yes, and for a few printed pieces it is the quickest fix. Make the sticker larger than
                    the old code, so it hides the whole pattern and the clear margin around it. If any of
                    the old pattern shows, some phones will read that instead. Scan the finished piece
                    with two phones before you leave it.
                </p>
                HTML,
        ];
    }

    /**
     * Free with no catch reads as a catch, so the page says why.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function whyStaticIsFree(): array
    {
        return [
            'id' => 'why-static-is-free',
            'question' => 'Why is a static code free?',
            'answer' => <<<'HTML'
                <p>
                    Because it costs us nothing once you have downloaded it. Your link is drawn into the
                    pattern, and scanning it never reaches us. We give it away so you can try us with
                    nothing to lose. Some people come back when they need a code they can edit, and
                    that is the part we charge for.
                </p>
                HTML,
        ];
    }

    /**
     * How a reader checks a code they already have, made here or anywhere.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function tellStaticFromDynamic(): array
    {
        return [
            'id' => 'tell-static-from-dynamic',
            'question' => 'How can I tell if a QR code is static?',
            'answer' => <<<'HTML'
                <p>
                    Scan it and look at the link your phone shows before it opens. If the link is the
                    page itself, such as your own website, the code is static and nothing sits between
                    the scan and the page. If it is a short link on another company's domain, the code
                    is dynamic, and that company can stop sending scans on.
                </p>
                HTML,
        ];
    }

    /**
     * What someone avoiding subscriptions asks next: whether the editable
     * code can be bought outright.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function payOnce(): array
    {
        return [
            'id' => 'pay-once',
            'question' => 'Can I pay once for a dynamic code instead of subscribing?',
            'answer' => <<<'HTML'
                <p>
                    No. We send every scan of a dynamic code on for as long as it is printed, so the work
                    is never finished and paid for. We would rather charge for that openly than sell a
                    one-off price that has to run out somewhere. If you want to pay nothing, ever, print
                    a static code.
                </p>
                HTML,
        ];
    }

    /**
     * The plan people make to keep the bill to one month, and where it ends.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function subscribeForOneMonth(): array
    {
        $graceDays = (int) config('subscription.grace_days');

        return [
            'id' => 'one-month-only',
            'question' => 'Can I subscribe for one month and keep the code working?',
            'answer' => <<<HTML
                <p>
                    Not after you cancel. The code works to the end of the month you paid for and
                    {$graceDays} more days, then it stops resolving. It stays in your account with its
                    scans, and works again as soon as you subscribe. One month suits a short campaign.
                    It does not suit a sign that stays up for years.
                </p>
                HTML,
        ];
    }

    /**
     * The fix that saves a static print run, when the address is the
     * reader's own.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function redirectTheOldAddress(): array
    {
        return [
            'id' => 'redirect-old-address',
            'question' => 'My code opens my own website. Can I change where it goes without reprinting?',
            'answer' => <<<'HTML'
                <p>
                    Yes, by redirecting the address rather than changing the code. Whoever runs your
                    website can send the old address on to the new page with a permanent redirect, and
                    many website builders have a setting for it. The printed code still opens the old
                    address, and the visitor lands on the new page a moment later.
                </p>
                HTML,
        ];
    }

    /**
     * A dynamic code is only editable where it was made, which matters when
     * the reader came to us hoping we could edit it.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function dynamicMadeElsewhere(): array
    {
        return [
            'id' => 'dynamic-made-elsewhere',
            'question' => 'Can you change a dynamic code I made somewhere else?',
            'answer' => <<<'HTML'
                <p>
                    No. Only the company whose short link is in the pattern can change where it goes, so
                    log in there and edit the destination. Moving the code to us would mean a new code
                    and a reprint. If you are reprinting anyway, that is the moment to choose where the
                    next one lives.
                </p>
                HTML,
        ];
    }

    /**
     * A restaurant prints the code many times, and the first thing it asks is
     * whether each copy has to be its own code.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function oneCodePerTable(): array
    {
        $dynamic = (int) config('subscription.quotas.dynamic');

        return [
            'id' => 'one-code-per-table',
            'question' => 'Do I need a different QR code for every table?',
            'answer' => <<<HTML
                <p>
                    No. One code can be printed as many times as you like, and every copy opens the same
                    menu. Use separate codes only when you want to count scans
                    separately, say for the terrace and the dining room, or for two sites. A
                    subscription covers {$dynamic} dynamic codes.
                </p>
                HTML,
        ];
    }

    /**
     * Most menus already exist as a PDF, so the question is whether that is
     * good enough to point a code at.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function menuAsPdf(): array
    {
        return [
            'id' => 'menu-as-pdf',
            'question' => 'Can the code open a PDF of my menu?',
            'answer' => <<<'HTML'
                <p>
                    Yes. A code opens any link, and a PDF with its own link works like any other page. We
                    do not host the file, so put it on your website or a file-sharing service first and
                    copy the link from there. A plain web page reads better on a phone than a PDF does,
                    but a PDF is a fine place to start.
                </p>
                HTML,
        ];
    }

    /**
     * An agent owns a few signs and many listings, so the first question is
     * whether a sign's code can move with it.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function signFollowsTheProperty(): array
    {
        return [
            'id' => 'sign-follows-the-property',
            'question' => 'Can one sign be used for property after property?',
            'answer' => <<<'HTML'
                <p>
                    Yes, if the code on it is dynamic. Give each sign its own code and, when the sign goes
                    up outside a new property, change that code to the new listing. It takes a minute on
                    your phone. Do it before the sign goes in the ground, so the first passer-by to scan
                    it does not land on a house that has already sold.
                </p>
                HTML,
        ];
    }

    /**
     * What an agent wants from the scans: proof of which sign is working.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function whichSignWasScanned(): array
    {
        return [
            'id' => 'which-sign-was-scanned',
            'question' => 'Can I see which of my signs people scan?',
            'answer' => <<<'HTML'
                <p>
                    Yes, as long as each sign carries its own dynamic code. Every code counts its own
                    scans, with the date and time, so you can tell a busy corner from a quiet street. We
                    do not know who scanned, and you will not get a name or a phone number from a scan.
                </p>
                HTML,
        ];
    }

    /**
     * The card is printed in hundreds, so the choice of destination matters
     * more than the choice of code.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function whatTheCardShouldOpen(): array
    {
        return [
            'id' => 'what-the-card-opens',
            'question' => 'What should the code on my business card open?',
            'answer' => <<<'HTML'
                <p>
                    One page that says who you are and how to reach you: a profile, a page on your
                    company's site, or a short page of your own with your number and email on it. Pick
                    the page someone would want a week after meeting you. A link that only works while
                    you are logged in, or a file in your own cloud folder, is a poor choice for a card.
                </p>
                HTML,
        ];
    }

    /**
     * The moment a business card code is most likely to go wrong.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function changingJobs(): array
    {
        return [
            'id' => 'changing-jobs',
            'question' => 'What happens to the code on my cards when I change jobs?',
            'answer' => <<<'HTML'
                <p>
                    A static code keeps opening the old page, which may be your old employer's site and
                    may soon be gone. A dynamic code can be pointed at your new profile the same day, so
                    the cards you already handed out lead to you rather than to the desk you left. Make
                    the code in an account of your own, not your employer's, so it moves with you.
                </p>
                HTML,
        ];
    }

    /**
     * Couples and organisers need a code for months, not years, and want to
     * know what that costs.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function subscribeForOneEvent(): array
    {
        $trialDays = (int) config('subscription.trial_days');

        return [
            'id' => 'subscribe-for-one-event',
            'question' => 'Do I have to subscribe for a single event?',
            'answer' => <<<HTML
                <p>
                    Only for the months between printing and the day itself. The {$trialDays}-day trial
                    lets you make the code and check it on the proof, but invitations usually go out
                    months ahead, so plan on the monthly plan from when they are sent until the event is
                    over. Then cancel. If nothing about the event will ever change, a free static code
                    is enough.
                </p>
                HTML,
        ];
    }

    /**
     * The honest end of an event code: it stops, unless it is kept.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function afterTheEvent(): array
    {
        $graceDays = (int) config('subscription.grace_days');

        return [
            'id' => 'after-the-event',
            'question' => 'What happens to the code after the event?',
            'answer' => <<<HTML
                <p>
                    That is up to you. Point it at the photo album and keep it, and guests who find the
                    invitation in a drawer will find the photos too. Or cancel, and {$graceDays} days
                    after your paid period ends the code shows a plain notice that it is not active.
                    Nobody who scans it is asked to pay.
                </p>
                HTML,
        ];
    }

    /**
     * A campaign prints thousands of copies, and the first worry is that
     * each one needs a code of its own.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function codePerBatch(): array
    {
        $dynamic = (int) config('subscription.quotas.dynamic');

        return [
            'id' => 'code-per-batch',
            'question' => 'Do I need a different code on every flyer?',
            'answer' => <<<HTML
                <p>
                    No. Every copy of a batch carries the same code. You need a separate code only for
                    each group you want to compare: one for the flyers at the station and one for the
                    posters in shops, say. A subscription covers {$dynamic} dynamic codes, so decide on
                    the groups before you send the files to the printer.
                </p>
                HTML,
        ];
    }

    /**
     * Posters stay on walls long after the date on them has passed.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function whenTheCampaignEnds(): array
    {
        return [
            'id' => 'when-the-campaign-ends',
            'question' => 'What should the code open once the campaign is over?',
            'answer' => <<<'HTML'
                <p>
                    Something that is still true. Posters stay up for months after an event, and flyers
                    turn up in coat pockets. Point the code at your next event, or at a page that says
                    the offer has ended and what you have now. That is better than a page that has gone,
                    and it puts the people still scanning to use.
                </p>
                HTML,
        ];
    }

    /**
     * A brand with many products has to decide how many codes to print
     * before the artwork is signed off.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function codePerProduct(): array
    {
        $dynamic = (int) config('subscription.quotas.dynamic');

        return [
            'id' => 'code-per-product',
            'question' => 'One code per product, or one for the whole range?',
            'answer' => <<<HTML
                <p>
                    One per product when each one needs its own page, such as instructions, ingredients
                    or a recall notice. One for the range when every pack opens the same place. A
                    subscription covers {$dynamic} dynamic codes, so a long range needs more, and the
                    question below says how to get them.
                </p>
                HTML,
        ];
    }

    /**
     * Static is free, so the page says when it is the right call on a pack.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function staticOnPackaging(): array
    {
        return [
            'id' => 'static-on-packaging',
            'question' => 'Is a static code ever right on packaging?',
            'answer' => <<<'HTML'
                <p>
                    Yes, if it opens an address on your own domain that you promise to keep, and you can
                    redirect that address when the page behind it moves. Plenty of brands do this. The
                    risk is the day the site is rebuilt and nobody remembers the address on the box.
                </p>
                HTML,
        ];
    }

    /**
     * The one step of this page that happens outside our site.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function findTheReviewLink(): array
    {
        return [
            'id' => 'find-the-review-link',
            'question' => 'Where do I find my Google review link?',
            'answer' => <<<'HTML'
                <p>
                    In your Google Business Profile. Sign in to the account that manages your business,
                    open your profile, and look for the option to ask for reviews. It gives you a short
                    link to copy. Open the link on your phone once, to check it lands on your review form,
                    then paste it into the generator above.
                </p>
                HTML,
        ];
    }

    /**
     * Why the free code is enough: the link belongs to the profile.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function reviewLinkAfterAMove(): array
    {
        return [
            'id' => 'review-link-after-a-move',
            'question' => 'Will the code still work if we move or rename the business?',
            'answer' => <<<'HTML'
                <p>
                    Usually, yes. The review link belongs to your Business Profile, not to your street
                    address or your name, so it stays the same as long as you update that profile rather
                    than start a new one. If you ever do start a new profile, print a new code for it.
                </p>
                HTML,
        ];
    }

    /**
     * The one rule a business can break with a review code, which is Google's
     * rule rather than ours.
     *
     * @return array{id: string, question: string, answer: string}
     */
    private static function askingForReviews(): array
    {
        return [
            'id' => 'asking-for-reviews',
            'question' => 'Can I offer a discount for a review?',
            'answer' => <<<'HTML'
                <p>
                    No. Google's rules do not allow rewards for reviews, and reviews bought that way can be
                    removed. Asking is fine. A code by the till with a plain line, such as "Enjoyed your
                    visit? Tell others", is all it takes.
                </p>
                HTML,
        ];
    }
}
