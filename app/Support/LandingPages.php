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
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'qr-code-expired',
                title: 'QR code expired? What happened and the fix',
                description: 'Your QR code says it has expired. What that usually means, whether it can be brought back, and how to print one that will not do it again.',
                h1: 'QR code expired? What happened, and how to fix it',
                keyword: 'qr code expired',
                related: ['qr-code-stopped-working', 'fix-printed-qr-code', 'free-qr-code-no-expiration'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'free-qr-code-no-expiration',
                title: 'Free QR code with no expiration',
                description: 'Make a static QR code that is free for good: no account, no trial, no date it stops. Generated on the page and downloaded straight away.',
                h1: 'A free QR code with no expiration, and no catch',
                keyword: 'free qr code no expiration',
                related: ['qr-code-without-subscription', 'qr-code-expired', 'google-review-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'qr-code-without-subscription',
                title: 'QR code generator with no subscription',
                description: 'Static QR codes need no subscription at all. If you need to edit a code after printing, here is what that costs and how to cancel.',
                h1: 'A QR code generator with no subscription',
                keyword: 'qr code generator no subscription',
                related: ['free-qr-code-no-expiration', 'qr-code-stopped-working', 'fix-printed-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'fix-printed-qr-code',
                title: 'Change a QR code after printing',
                description: 'Whether the QR code you already printed can be changed, what to do if it cannot, and how to make the next print run editable.',
                h1: 'How to change a QR code after printing',
                keyword: 'change qr code after printing',
                related: ['qr-code-stopped-working', 'product-packaging-qr-code', 'restaurant-menu-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'restaurant-menu-qr-code',
                title: 'QR code for a restaurant menu',
                description: 'Put one QR code on every table and change the menu behind it whenever the dishes or prices change, without reprinting a single table tent.',
                h1: 'A QR code for your restaurant menu',
                keyword: 'qr code for restaurant menu',
                related: ['flyer-poster-qr-code', 'event-qr-code', 'fix-printed-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'real-estate-qr-code',
                title: 'QR code for a real estate sign',
                description: 'Print one QR code on your sign and point it at whichever listing the sign is standing outside, then move it to the next property.',
                h1: 'A QR code for your real estate sign',
                keyword: 'real estate sign qr code',
                related: ['flyer-poster-qr-code', 'business-card-qr-code', 'fix-printed-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'business-card-qr-code',
                title: 'QR code for a business card',
                description: 'A QR code on your business card that you can point somewhere new when your job or your link changes, long before the cards run out.',
                h1: 'A QR code for your business card',
                keyword: 'qr code for business card',
                related: ['real-estate-qr-code', 'event-qr-code', 'flyer-poster-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'event-qr-code',
                title: 'QR code for an event or wedding',
                description: 'Put a QR code on the invitation and update the schedule, the venue or the photo album behind it after the invitations are printed.',
                h1: 'A QR code for your event or wedding',
                keyword: 'qr code for event',
                related: ['flyer-poster-qr-code', 'business-card-qr-code', 'restaurant-menu-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'flyer-poster-qr-code',
                title: 'QR code for a flyer or poster',
                description: 'A QR code for each batch of flyers or posters, so you can see which batch is being scanned and change where it points mid-campaign.',
                h1: 'A QR code for your flyer or poster',
                keyword: 'qr code for flyer',
                related: ['event-qr-code', 'real-estate-qr-code', 'product-packaging-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'product-packaging-qr-code',
                title: 'QR code on product packaging',
                description: 'Packaging stays on shelves and in homes for years. Put a QR code on it whose destination you can still change when your site moves.',
                h1: 'A QR code on your product packaging',
                keyword: 'qr code on packaging',
                related: ['fix-printed-qr-code', 'flyer-poster-qr-code', 'restaurant-menu-qr-code'],
                faq: [],
                published: false,
            ),
            new LandingPage(
                slug: 'google-review-qr-code',
                title: 'QR code for Google reviews',
                description: 'Make a free QR code that opens your Google review page, print it at the till or on the receipt, and let customers review you in one scan.',
                h1: 'A QR code for Google reviews',
                keyword: 'qr code for google reviews',
                related: ['free-qr-code-no-expiration', 'restaurant-menu-qr-code', 'business-card-qr-code'],
                faq: [],
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
}
