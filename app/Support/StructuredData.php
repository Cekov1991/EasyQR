<?php

namespace App\Support;

use App\Enums\BillingInterval;
use App\Enums\Plan;

/**
 * The JSON-LD an AI crawler and a search engine read instead of guessing.
 *
 * Prose tells a reader that dynamic codes cost forty-nine dollars a year; this
 * says it in the one vocabulary a machine does not have to parse out of a
 * sentence. It matters here because an assistant answering "what does it cost"
 * is quoting whatever it can state with confidence, and an unmarked price on a
 * page of marketing copy is not that.
 *
 * Every value comes from config, so the marked-up price cannot drift from the
 * price the customer is actually charged — the same reason SubscriptionPrice
 * exists.
 *
 * Split in two because the entities have different scopes. The organisation and
 * the site are true on the refund policy as much as on the homepage, so the
 * layout emits them everywhere. The product and its offers belong to the pages
 * that sell it, and marking a legal notice up as a software listing muddies
 * which page is the one about the product.
 */
class StructuredData
{
    /**
     * Who runs this and what the site is. Emitted on every public page.
     */
    public static function forSite(): string
    {
        return self::encode([
            self::organization(),
            [
                '@type' => 'WebSite',
                '@id' => url('/#website'),
                'name' => config('app.name'),
                'url' => url('/'),
                'description' => config('site.share.description'),
                'inLanguage' => 'en',
                'publisher' => ['@id' => url('/#organization')],
            ],
        ]);
    }

    /**
     * The product and what it costs. Emitted on the pages that sell it.
     */
    public static function forProduct(): string
    {
        return self::encode([
            [
                '@type' => 'SoftwareApplication',
                '@id' => url('/#software'),
                'name' => config('app.name'),
                'url' => url('/'),
                'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'QR code generator',
                'operatingSystem' => 'Web browser',
                'description' => config('site.share.description'),
                'image' => asset(config('site.share.image')),
                'publisher' => ['@id' => url('/#organization')],
                'featureList' => [
                    'Free static QR codes with no account',
                    'PNG and SVG download',
                    'Dynamic QR codes with an editable destination',
                    'Scan tracking and analytics',
                ],
                'offers' => [
                    self::freeOffer(),
                    self::paidOffer(Plan::Monthly),
                    self::paidOffer(Plan::Yearly),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/#organization'),
            'name' => config('site.operator.name'),
            'url' => url('/'),
            'email' => config('site.support_email'),
            'taxID' => config('site.operator.tax_id'),
            'address' => config('site.operator.address'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function freeOffer(): array
    {
        return [
            '@type' => 'Offer',
            'name' => 'Static QR code',
            'description' => 'Unlimited static QR codes, generated on the page and downloaded immediately. No account, no expiry.',
            'price' => '0',
            'priceCurrency' => SubscriptionPrice::currency(),
            'availability' => 'https://schema.org/InStock',
            'url' => route('pricing'),
        ];
    }

    /**
     * One plan, priced the way it is charged: tax inclusive, because AgentaOS
     * is merchant of record and carves destination VAT out of this amount
     * rather than adding it on top.
     *
     * Every plan gets its own offer, each named for the plan it prices. An
     * assistant reading a lone offer would quote it as the only price there is.
     *
     * @return array<string, mixed>
     */
    private static function paidOffer(Plan $plan): array
    {
        $price = self::price($plan);
        $currency = SubscriptionPrice::currency();

        return [
            '@type' => 'Offer',
            'name' => 'Dynamic QR codes, '.$plan->label(),
            'description' => 'Dynamic QR codes with an editable destination and scan analytics, after a '.config('subscription.trial_days').'-day free trial.',
            'price' => $price,
            'priceCurrency' => $currency,
            'availability' => 'https://schema.org/InStock',
            'url' => route('pricing'),
            'priceSpecification' => [
                '@type' => 'UnitPriceSpecification',
                'price' => $price,
                'priceCurrency' => $currency,
                'valueAddedTaxIncluded' => true,
                'billingDuration' => 1,
                'billingIncrement' => 1,
                'unitCode' => self::billingUnitCode($plan),
            ],
        ];
    }

    /**
     * "49.00". Schema.org wants a plain decimal with no symbol and no grouping
     * separator, which is the opposite of what SubscriptionPrice renders.
     */
    private static function price(Plan $plan): string
    {
        return number_format($plan->price(), 2, '.', '');
    }

    /**
     * The UN/CEFACT code for the billing period, which is what
     * UnitPriceSpecification expects rather than the word "year".
     */
    private static function billingUnitCode(Plan $plan): string
    {
        return match ($plan->interval()) {
            BillingInterval::Month => 'MON',
            BillingInterval::Year => 'ANN',
        };
    }

    /**
     * JSON_HEX_TAG matters: this is interpolated raw inside a <script> element,
     * so a stray "</script>" in a config value would close the block early and
     * spill the rest onto the page.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function encode(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
