<?php

namespace Tests\Feature;

use App\Enums\Plan;
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
        foreach (['/', '/pricing'] as $path) {
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
     * The marked-up price against the configured one. These are the two numbers
     * that must never disagree: one is quoted to a stranger by an assistant, the
     * other is charged to a card.
     */
    public function test_the_marked_up_price_is_the_configured_price(): void
    {
        $application = $this->node($this->graphFrom($this->get('/pricing')), 'SoftwareApplication');

        $paid = collect($application['offers'])->firstWhere('price', '!=', '0');

        $this->assertSame(number_format(Plan::Yearly->price(), 2, '.', ''), $paid['price']);
        $this->assertSame(config('subscription.currency'), $paid['priceCurrency']);
        $this->assertTrue(
            $paid['priceSpecification']['valueAddedTaxIncluded'],
            'AgentaOS carves destination VAT out of this amount, so the price is tax inclusive.'
        );
        $this->assertSame('ANN', $paid['priceSpecification']['unitCode'], 'The subscription is billed yearly.');
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
        foreach (['/', '/pricing', '/report', '/terms-and-conditions', '/privacy-policy', '/refund-policy'] as $path) {
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
