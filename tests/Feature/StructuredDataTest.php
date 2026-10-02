<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Support\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The JSON-LD a machine reads instead of parsing our prose.
 *
 * Worth testing for the same reason the price lives in config: an assistant
 * asked what this costs will quote the marked-up number, and a stale one is a
 * wrong price stated with more authority than the sentence next to it.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_public_page_declares_who_operates_the_site(): void
    {
        foreach (['/', '/pricing', '/privacy-policy', '/report'] as $path) {
            $graph = $this->graphFrom($this->get($path));

            $organization = $this->node($graph, 'Organization');

            $this->assertNotNull($organization, "No Organization markup on {$path}.");
            $this->assertSame(config('site.operator.name'), $organization['name']);
            $this->assertSame(config('site.operator.tax_id'), $organization['taxID']);
        }
    }

    /**
     * A legal notice is not a software listing. Marking every page as the
     * product blurs which page is the one about the product.
     */
    public function test_only_the_pages_that_sell_it_are_marked_up_as_the_product(): void
    {
        foreach (['/', '/pricing', '/faq'] as $path) {
            $this->assertNotNull(
                $this->node($this->graphFrom($this->get($path)), 'SoftwareApplication'),
                "The product markup is missing from {$path}."
            );
        }

        foreach (['/privacy-policy', '/terms-and-conditions', '/refund-policy', '/report'] as $path) {
            $this->assertNull(
                $this->node($this->graphFrom($this->get($path)), 'SoftwareApplication'),
                "{$path} is marked up as a software listing, which it is not."
            );
        }
    }

    /**
     * The marked-up prices against the configured ones. These are the numbers
     * that must never disagree: one is quoted to a stranger by an assistant,
     * the other is charged to a card.
     *
     * Both plans are marked up, each with its own billing period, because an
     * assistant reading a single offer would quote it as the only price there
     * is.
     */
    public function test_every_plan_is_marked_up_at_its_configured_price_and_period(): void
    {
        $application = $this->node($this->graphFrom($this->get('/pricing')), 'SoftwareApplication');

        $paid = collect($application['offers'])->where('price', '!=', '0')->values();

        $this->assertCount(2, $paid, 'Both plans should be offered.');

        $expected = [
            Plan::Monthly->value => 'MON',
            Plan::Yearly->value => 'ANN',
        ];

        foreach (Plan::cases() as $plan) {
            $offer = $paid->firstWhere('price', number_format($plan->price(), 2, '.', ''));

            $this->assertNotNull($offer, "The {$plan->value} plan is not marked up at its configured price.");
            $this->assertSame(config('subscription.currency'), $offer['priceCurrency']);
            $this->assertStringContainsString(
                $plan->label(),
                $offer['name'],
                'Each offer should name the plan it prices, or the two are indistinguishable.'
            );
            $this->assertTrue(
                $offer['priceSpecification']['valueAddedTaxIncluded'],
                'AgentaOS carves destination VAT out of this amount, so the price is tax inclusive.'
            );
            $this->assertSame($expected[$plan->value], $offer['priceSpecification']['unitCode']);
        }
    }

    public function test_the_free_tier_is_marked_up_as_free(): void
    {
        $application = $this->node($this->graphFrom($this->get('/')), 'SoftwareApplication');

        $free = collect($application['offers'])->firstWhere('price', '0');

        $this->assertNotNull($free, 'Nothing says static QR codes cost nothing, which is the reason most people arrive.');
        $this->assertSame('https://schema.org/InStock', $free['availability']);
    }

    /**
     * Rendered raw inside a <script> element, so a broken value does not fail
     * loudly — it silently produces markup no crawler can read.
     */
    public function test_the_markup_on_every_public_page_is_parseable(): void
    {
        foreach (['/', '/pricing', '/faq', '/report', '/terms-and-conditions', '/privacy-policy', '/refund-policy'] as $path) {
            $this->assertNotEmpty($this->graphFrom($this->get($path)), "No parseable JSON-LD on {$path}.");
        }
    }

    /**
     * One indexable URL per page, matching og:url, so a campaign tag cannot
     * split a page into several in an index.
     */
    public function test_a_page_is_canonical_at_its_own_url_without_the_query_string(): void
    {
        $this->get('/pricing?utm_source=newsletter&ref=x')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/pricing').'">', false)
            ->assertDontSee('utm_source', false);
    }

    /**
     * forFaq() was generalised so Landing Pages could mark up their own
     * questions. The FAQ page's markup must read exactly as it did: every
     * question anchored to /faq, under the page's own name.
     */
    public function test_the_faq_page_markup_is_anchored_to_the_faq_page(): void
    {
        $faq = $this->node($this->graphFrom($this->get('/faq')), 'FAQPage');

        $this->assertNotNull($faq);
        $this->assertSame(route('faq').'#faq', $faq['@id']);
        $this->assertSame(route('faq'), $faq['url']);
        $this->assertSame('Frequently asked questions', $faq['name']);
        $this->assertSame(['@id' => url('/#organization')], $faq['publisher']);
        $this->assertSame(
            array_map(fn (array $entry): string => route('faq').'#'.$entry['id'], Faq::questions()),
            array_column($faq['mainEntity'], '@id'),
        );
    }

    /**
     * Every JSON-LD node on the page, flattened out of the @graph wrappers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function graphFrom(TestResponse $response): array
    {
        $response->assertOk();

        preg_match_all(
            '~<script type="application/ld\+json">(.*?)</script>~s',
            $response->getContent(),
            $matches
        );

        $nodes = [];

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);

            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'A JSON-LD block did not parse: '.json_last_error_msg());
            $this->assertSame('https://schema.org', $decoded['@context']);

            $nodes = array_merge($nodes, $decoded['@graph']);
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $graph
     * @return array<string, mixed>|null
     */
    private function node(array $graph, string $type): ?array
    {
        return collect($graph)->firstWhere('@type', $type);
    }
}
