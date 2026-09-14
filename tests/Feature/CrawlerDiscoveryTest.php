<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Support\PublicPages;
use App\Support\SubscriptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a crawler sees. None of this is reachable from the UI, so nothing in the
 * product breaks when it is wrong — the same blind spot that let the site ship
 * for months with no og: tags at all.
 *
 * The failures these guard against are all silent: a static file shadowing the
 * route, a named crawler group that accidentally revokes the shared rules, a
 * sitemap advertising a URL that 404s, a marked-up price that no longer matches
 * the one being charged.
 */
class CrawlerDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The web server serves a real file in preference to a route, so a
     * public/robots.txt would make every rule below dead code that still tests
     * green. There used to be one; this is why it is gone.
     */
    public function test_no_static_file_shadows_the_crawler_routes(): void
    {
        foreach (['robots.txt', 'sitemap.xml', 'llms.txt'] as $file) {
            $this->assertFileDoesNotExist(
                public_path($file),
                "public/{$file} would be served instead of the route that builds it."
            );
        }
    }

    public function test_robots_is_served_as_plain_text(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * The Sitemap directive takes an absolute URL, so a hardcoded one is wrong
     * everywhere except production. Built by the router for that reason.
     */
    public function test_robots_points_at_the_sitemap_absolutely(): void
    {
        $this->get('/robots.txt')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    /**
     * The retrieval crawlers are the point of the exercise: they fetch a page at
     * the moment someone asks an assistant a question, and blocking them is what
     * makes a site invisible in answers.
     */
    public function test_the_crawlers_that_decide_citations_are_welcome(): void
    {
        $robots = $this->get('/robots.txt')->getContent();

        foreach (['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'GPTBot', 'ClaudeBot', 'Google-Extended'] as $agent) {
            $this->assertStringContainsString("User-agent: {$agent}\n", $robots);
        }
    }

    /**
     * A named group replaces the `User-agent: *` group outright rather than
     * adding to it, so naming a crawler in order to welcome it hands it the
     * whole site unless the shared rules are repeated underneath. Every group
     * must carry the full list.
     */
    public function test_every_group_repeats_the_closed_paths(): void
    {
        $groups = $this->robotsGroups();

        $this->assertGreaterThan(1, count($groups), 'Expected the wildcard group plus at least one named group.');

        foreach ($groups as $agents => $rules) {
            foreach (PublicPages::closedPaths() as $path) {
                $this->assertContains(
                    "Disallow: {$path}",
                    $rules,
                    "The group for {$agents} does not disallow {$path}, so naming those crawlers has opened it to them."
                );
            }
        }
    }

    /**
     * Resolving a short link writes a scan row, so a crawler walking these URLs
     * bills phantom scans to a customer's analytics and follows the redirect to
     * a destination we do not control.
     */
    public function test_short_links_are_closed_to_everyone(): void
    {
        foreach ($this->robotsGroups() as $agents => $rules) {
            $this->assertContains('Disallow: /q/', $rules, "Short links are crawlable by {$agents}.");
        }
    }

    public function test_the_sitemap_is_well_formed_xml(): void
    {
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->getContent());
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, 'The sitemap did not parse as XML.');
        $this->assertSame('urlset', $xml->getName());
    }

    public function test_the_sitemap_lists_every_public_page(): void
    {
        $response = $this->get('/sitemap.xml');

        foreach (PublicPages::all() as $page) {
            $response->assertSee('<loc>'.$page['url'].'</loc>', false);
        }
    }

    /**
     * A sitemap is a promise that these URLs exist. One that 404s or redirects
     * costs crawl budget and reads as a neglected site.
     */
    public function test_every_page_the_sitemap_advertises_actually_resolves(): void
    {
        foreach (PublicPages::all() as $page) {
            $this->get($page['url'])->assertOk();
        }
    }

    /**
     * The two lists are complements, so a path appearing in both means one of
     * them is wrong — and the sitemap would be inviting a crawl of something
     * robots.txt forbids.
     */
    public function test_the_sitemap_advertises_nothing_robots_closes(): void
    {
        foreach (PublicPages::all() as $page) {
            $path = parse_url($page['url'], PHP_URL_PATH) ?: '/';

            foreach (PublicPages::closedPaths() as $closed) {
                $this->assertFalse(
                    str_starts_with($path, $closed),
                    "The sitemap lists {$page['url']}, which robots.txt disallows via {$closed}."
                );
            }
        }
    }

    /**
     * Both prices explicitly, never a "from": this text is written for a
     * machine that will paraphrase it, and a paraphrase of one price reads as
     * the only price there is.
     */
    public function test_llms_states_both_prices_that_are_actually_charged(): void
    {
        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee(SubscriptionPrice::everyPriceInProse(), false)
            ->assertSee(SubscriptionPrice::formatted(Plan::Monthly), false)
            ->assertSee(SubscriptionPrice::formatted(Plan::Yearly), false)
            ->assertSee((string) config('subscription.trial_days').'-day free trial', false);
    }

    public function test_llms_links_every_page_the_sitemap_does(): void
    {
        $response = $this->get('/llms.txt');

        foreach (PublicPages::all() as $page) {
            $response->assertSee('['.$page['title'].']('.$page['url'].')', false);
        }
    }

    /**
     * Robots.txt parsed into groups: the joined user-agent lines of each group
     * mapped to the rules that follow them.
     *
     * @return array<string, array<int, string>>
     */
    private function robotsGroups(): array
    {
        $lines = preg_split('/\R/', $this->get('/robots.txt')->getContent());

        $groups = [];
        $agents = [];
        $rules = [];

        $flush = function () use (&$groups, &$agents, &$rules): void {
            if ($agents !== []) {
                $groups[implode(', ', $agents)] = $rules;
            }

            $agents = [];
            $rules = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'User-agent:')) {
                if ($rules !== []) {
                    $flush();
                }

                $agents[] = trim(substr($line, strlen('User-agent:')));

                continue;
            }

            if ($agents !== []) {
                $rules[] = $line;
            }
        }

        $flush();

        return $groups;
    }
}
