<?php

namespace App\Support;

/**
 * What a crawler should and should not fetch.
 *
 * The sitemap and llms.txt both render from `all()` rather than keeping their
 * own copies, and every named group in robots.txt renders from `closedPaths()`,
 * so a page cannot appear in one file and be missing from another, and a route
 * that moves is corrected in one place.
 *
 * Membership is a decision, not a mirror of the router.
 */
class PublicPages
{
    /**
     * The pages we want found, and what each one is for.
     *
     * The summaries are written for a machine that will paraphrase them, not
     * for a person reading a menu, so each one states the fact a reader would
     * actually be asking for rather than restating the page title.
     *
     * @return array<int, array{url: string, title: string, summary: string}>
     */
    public static function all(): array
    {
        return [...self::fixed(), ...self::landingPages()];
    }

    /**
     * @return array<int, array{url: string, title: string, summary: string}>
     */
    private static function fixed(): array
    {
        return [
            [
                'url' => route('welcome'),
                'title' => 'Create a QR code',
                'summary' => 'Generate a free static QR code in the browser with no account, or a dynamic QR code whose destination can be changed after it is printed.',
            ],
            [
                'url' => route('pricing'),
                'title' => 'Pricing',
                'summary' => 'Static QR codes are free forever. Dynamic QR codes cost '.SubscriptionPrice::everyPriceInProse().', tax included, after a '.config('subscription.trial_days').'-day free trial that needs no payment details.',
            ],
            [
                'url' => route('faq'),
                'title' => 'Frequently asked questions',
                'summary' => 'Whether a printed QR code can be edited, what a static code cannot do, what the subscription costs and what happens to a dynamic code when it lapses.',
            ],
            [
                'url' => route('report.create'),
                'title' => 'Report a QR code',
                'summary' => 'Report a QR code on this domain that leads somewhere harmful. Open to anyone, no account needed.',
            ],
            [
                'url' => url('/terms-and-conditions'),
                'title' => 'Terms and Conditions',
                'summary' => 'The contract between '.config('site.operator.name').' and the customer, including what happens to a dynamic QR code when a subscription lapses.',
            ],
            [
                'url' => url('/privacy-policy'),
                'title' => 'Privacy Policy',
                'summary' => 'What is recorded when a QR code is scanned, how long scan data is kept, and every processor that handles it.',
            ],
            [
                'url' => url('/refund-policy'),
                'title' => 'Refund Policy',
                'summary' => 'How refunds work for the dynamic QR code subscription.',
            ],
        ];
    }

    /**
     * Published Landing Pages only: an unpublished one has no route, and a
     * sitemap entry for it would advertise a 404.
     *
     * @return array<int, array{url: string, title: string, summary: string}>
     */
    private static function landingPages(): array
    {
        return array_values(array_map(fn (LandingPage $page): array => [
            'url' => $page->url(),
            'title' => $page->title,
            'summary' => $page->description,
        ], LandingPages::published()));
    }

    /**
     * Paths no crawler should fetch, whoever it is.
     *
     * A named group in robots.txt replaces the `User-agent: *` group outright
     * rather than adding to it, so naming a crawler in order to welcome it would
     * silently hand it the whole site unless these are repeated underneath it.
     * Every group renders this list for that reason.
     *
     * /q/ is the one that is not merely pointless. Resolving a short link writes
     * a scan row, so a crawler walking those URLs bills phantom scans to a
     * customer's analytics and follows the redirect to a third-party
     * destination we do not control.
     *
     * Every entry is a prefix, so a path that can be the start of a slug needs
     * its trailing slash.
     *
     * @return array<int, string>
     */
    public static function closedPaths(): array
    {
        return [
            '/q/',
            '/admin',
            '/dashboard',
            '/profile',
            '/billing',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/confirm-password',
            '/webhooks',
            '/storage',
            '/livewire',
            '/cookies',
        ];
    }
}
