<?php

namespace App\Enums;

use App\Models\SiteEvent;

/**
 * The things we count.
 *
 * This exists because the funnel from "stranger lands on the homepage" to
 * "subscription row" was entirely invisible. Every step of it already happened
 * in code we own; none of it was ever written down, so questions as basic as
 * "how many people generate a code and then look at the price" had no answer.
 *
 * Two rules govern the whole design, and they are the reason this is an
 * anonymous counter rather than analytics:
 *
 * 1. A row never identifies anyone. No user id, no session id, no IP, no user
 *    agent — enforced by EventPrivacyTest, not by good intentions. Section 2 of
 *    the Privacy Policy says we build no profile of the pages you visit, and a
 *    single identifier column would make that false. Where per-person
 *    attribution is genuinely needed it lives on the user's own row as
 *    `users.signup_source`, which is one durable fact about their account
 *    rather than a stream of their behaviour.
 *
 * 2. Context stays low-cardinality. A handful of known labels, never a URL and
 *    never anything a visitor typed. The moment this holds free text it has
 *    become a request log with a query planner attached.
 *
 * Page views are deliberately absent. They are the highest-volume row by far,
 * the only candidate with no action behind them, and precisely what the Privacy
 * Policy disclaims. We count things people did, not places they were.
 */
enum TrackedEvent: string
{
    /**
     * A free static code was drawn: the first time a visitor's link was valid on
     * a page view. Reported by the browser, because the link never leaves it and
     * the server has no request to count from. The denominator for everything
     * else: without it, a count of offer clicks divides by nothing.
     */
    case StaticQrGenerated = 'static_qr_generated';

    /** The static code was downloaded. `context.format` is png or svg. */
    case QrDownloaded = 'qr_downloaded';

    /** The subscription offer was rendered. `variant` carries the arm. */
    case OfferShown = 'offer_shown';

    case OfferDismissed = 'offer_dismissed';

    case OfferClicked = 'offer_clicked';

    /** An AgentaOS checkout was opened. */
    case CheckoutStarted = 'checkout_started';

    /** The buyer came back through the success URL. */
    case CheckoutCompleted = 'checkout_completed';

    /** The buyer came back through the cancel URL. */
    case CheckoutAbandoned = 'checkout_abandoned';

    /**
     * Whether a browser may raise this event through the public endpoint.
     *
     * The trust split, and the reason it matters: the events below happen only
     * in the browser, so they have to be reported by one. The rest are recorded
     * server-side on paths we control, and if the public route accepted them
     * too, anyone could POST CheckoutCompleted ten thousand times and quietly
     * poison the only record we have of revenue — undetectably, because there is
     * no identifier here to spot a flood with.
     *
     * A new case is therefore untrusted by default: it has to be named here to
     * become client-loggable.
     */
    public function isClientLoggable(): bool
    {
        return match ($this) {
            self::StaticQrGenerated,
            self::QrDownloaded,
            self::OfferShown,
            self::OfferDismissed,
            self::OfferClicked => true,
            default => false,
        };
    }

    /**
     * The cases a browser may report, for validating the public endpoint.
     *
     * @return array<int, string>
     */
    public static function clientLoggableValues(): array
    {
        return array_values(array_map(
            fn (self $event): string => $event->value,
            array_filter(self::cases(), fn (self $event): bool => $event->isClientLoggable()),
        ));
    }

    /**
     * Count one occurrence.
     *
     * Deliberately fire-and-forget and deliberately unqueued: this is one small
     * insert with no reads, and it runs on request paths — the events endpoint and
     * the checkout — where a queue round trip would cost more than the write. A
     * failure here must never break the thing being counted, so callers get no
     * return value and nothing to check.
     *
     * @param  array<string, scalar>|null  $context  Low-cardinality labels only.
     */
    public function record(?string $variant = null, ?array $context = null): void
    {
        SiteEvent::create([
            'name' => $this->value,
            'variant' => $variant,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
