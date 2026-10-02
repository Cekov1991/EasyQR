<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Support\Faq;
use App\Support\LandingPage;
use App\Support\LandingPages;
use App\Support\PublicPages;
use App\Support\StructuredData;
use App\Support\SubscriptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The pages written for one search a stranger types.
 *
 * A Landing Page exists only once a human has read and published it, so most
 * of what is guarded here is absence: an unpublished page has no route, no
 * sitemap entry and no link pointing at it from anywhere.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private const HUB = LandingPages::HUB;

    public function test_the_registry_lists_every_page_in_the_spec(): void
    {
        $this->assertSame([
            'static-vs-dynamic-qr-code',
            'qr-code-stopped-working',
            'qr-code-expired',
            'free-qr-code-no-expiration',
            'qr-code-without-subscription',
            'fix-printed-qr-code',
            'restaurant-menu-qr-code',
            'real-estate-qr-code',
            'business-card-qr-code',
            'event-qr-code',
            'flyer-poster-qr-code',
            'product-packaging-qr-code',
            'google-review-qr-code',
        ], array_keys(LandingPages::all()));
    }

    /**
     * Copy is written unpublished and a person reads it before it goes live.
     */
    public function test_nothing_is_published_until_a_person_has_read_it(): void
    {
        $this->assertSame([], LandingPages::published());
    }

    public function test_every_related_slug_names_a_page_in_the_registry(): void
    {
        foreach (LandingPages::all() as $page) {
            foreach ($page->related as $slug) {
                $this->assertArrayHasKey($slug, LandingPages::all(), "{$page->slug} relates to {$slug}, which does not exist.");
                $this->assertNotSame($page->slug, $slug, "{$page->slug} relates to itself.");
            }
        }
    }

    /**
     * A reused answer is named by its id, so a renamed id must fail here
     * rather than on a page in production.
     */
    public function test_every_reused_faq_id_resolves(): void
    {
        foreach (LandingPages::all() as $page) {
            foreach ($page->faq() as $entry) {
                $this->assertNotEmpty($entry['question'], "{$page->slug} has an empty question.");
            }
        }
    }

    public function test_an_unknown_faq_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Faq::find('no-such-question');
    }

    public function test_an_unpublished_page_has_no_route(): void
    {
        $this->get('/'.self::HUB)->assertNotFound();
    }

    public function test_an_unknown_slug_has_no_route(): void
    {
        $this->get('/qr-code-for-nothing-at-all')->assertNotFound();
    }

    public function test_a_published_page_is_served(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/'.self::HUB)
            ->assertOk()
            ->assertViewIs('landing.'.self::HUB)
            ->assertSee('<h1 class="eq-h1">'.e(LandingPages::find(self::HUB)->h1).'</h1>', false);
    }

    /**
     * Publishing one page must not quietly publish the others.
     */
    public function test_publishing_one_page_leaves_the_rest_unpublished(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/qr-code-stopped-working')->assertNotFound();
        $this->assertSame([self::HUB], array_keys(LandingPages::published()));
    }

    /**
     * The layout appends the site name, so the registry's title has to leave
     * room for it.
     */
    public function test_every_page_fits_in_a_search_result_once_published(): void
    {
        foreach (LandingPages::all() as $page) {
            $this->assertLessThanOrEqual(60, mb_strlen($page->title.' - '.config('app.name')), "{$page->slug}'s title is cut off.");
            $this->assertLessThanOrEqual(155, mb_strlen($page->description), "{$page->slug}'s description is cut off.");
        }
    }

    public function test_every_page_has_its_own_title_and_description(): void
    {
        $pages = collect(LandingPages::all());

        $this->assertSame($pages->count(), $pages->pluck('title')->unique()->count(), 'Two pages share a title.');
        $this->assertSame($pages->count(), $pages->pluck('description')->unique()->count(), 'Two pages share a description.');
    }

    /**
     * The pages whose copy is written: the voice pilot first, then the pages
     * drafted in the voice it set. Each still waits for a person to publish it.
     *
     * @return array<string, array{0: string}>
     */
    public static function draftedPageProvider(): array
    {
        return [
            'hub' => [self::HUB],
            'stopped working' => ['qr-code-stopped-working'],
            'expired' => ['qr-code-expired'],
            'no expiration' => ['free-qr-code-no-expiration'],
            'no subscription' => ['qr-code-without-subscription'],
            'fix printed' => ['fix-printed-qr-code'],
            'restaurant menu' => ['restaurant-menu-qr-code'],
            'real estate' => ['real-estate-qr-code'],
            'business card' => ['business-card-qr-code'],
            'event' => ['event-qr-code'],
            'flyer or poster' => ['flyer-poster-qr-code'],
            'packaging' => ['product-packaging-qr-code'],
            'google review' => ['google-review-qr-code'],
        ];
    }

    /**
     * The use cases that realistically need more dynamic codes than a
     * subscription covers: a sign per property, a code per batch, a code per
     * product line.
     *
     * @return array<string, array{0: string}>
     */
    public static function pageThatOutgrowsTheQuotaProvider(): array
    {
        return [
            'real estate' => ['real-estate-qr-code'],
            'flyer or poster' => ['flyer-poster-qr-code'],
            'packaging' => ['product-packaging-qr-code'],
        ];
    }

    /**
     * Every drafted page except the one written for the searcher who typed
     * "expired".
     *
     * @return array<string, array{0: string}>
     */
    public static function draftedPageNotAboutExpiryProvider(): array
    {
        return array_filter(self::draftedPageProvider(), fn (array $case): bool => $case[0] !== 'qr-code-expired');
    }

    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_fits_in_a_search_result(string $slug): void
    {
        $this->publishLandingPage($slug);

        $html = $this->get('/'.$slug)->assertOk()->getContent();

        preg_match('~<title>(.*?)</title>~s', $html, $title);
        preg_match('~<meta name="description" content="(.*?)">~s', $html, $description);

        $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode($title[1])), 'The title is cut off in a search result.');
        $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($description[1])), 'The description is cut off in a search result.');
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/'.$slug).'">', $html);
    }

    /**
     * A searched phrase is rarely a sentence ("qr code generator no
     * subscription"), so the H1 carries every word of it in an order a
     * person would write.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_says_what_it_is_about_in_its_h1(string $slug): void
    {
        $page = LandingPages::find($slug);

        $words = preg_split('~\W+~', strtolower($page->h1), -1, PREG_SPLIT_NO_EMPTY);

        foreach (explode(' ', $page->keyword) as $word) {
            $this->assertContains($word, $words, "{$slug}'s H1 leaves out \"{$word}\" from its keyword.");
        }
    }

    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_has_related_pages(string $slug): void
    {
        $page = LandingPages::find($slug);

        $this->publishLandingPage(...array_keys(LandingPages::all()));

        $this->assertNotEmpty(LandingPages::relatedTo($page));
    }

    /**
     * Long enough to answer the search properly, short enough to read on a
     * phone. Counted from the answer under the H1 to the call to action,
     * the FAQ included.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_is_the_length_the_spec_sets(string $slug): void
    {
        $this->publishLandingPage($slug);

        $words = $this->bodyWordCount($this->get('/'.$slug));

        $this->assertGreaterThanOrEqual(600, $words, "{$slug} has {$words} words of body copy.");
        $this->assertLessThanOrEqual(1000, $words, "{$slug} has {$words} words of body copy.");
    }

    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_is_marked_up_as_questions_and_as_the_product(string $slug): void
    {
        $this->publishLandingPage($slug);

        $graph = $this->graphFrom($this->get('/'.$slug));

        $this->assertNotNull($this->node($graph, 'SoftwareApplication'));

        $faq = $this->node($graph, 'FAQPage');
        $page = LandingPages::find($slug);

        $this->assertNotNull($faq);
        $this->assertSame(url('/'.$slug), $faq['url']);
        $this->assertSame(url('/'.$slug).'#faq', $faq['@id']);
        $this->assertCount(count($page->faq()), $faq['mainEntity']);
        $this->assertGreaterThanOrEqual(3, count($faq['mainEntity']));
        $this->assertLessThanOrEqual(5, count($faq['mainEntity']));

        foreach ($faq['mainEntity'] as $question) {
            $this->assertSame('Question', $question['@type']);
            $this->assertStringStartsWith(url('/'.$slug).'#', $question['@id']);
            $this->assertNotEmpty($question['acceptedAnswer']['text']);
        }
    }

    /**
     * An existing answer is never reworded: a page quotes the FAQ page.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_reused_answers_read_exactly_as_they_do_on_the_faq_page(string $slug): void
    {
        $this->publishLandingPage($slug);

        $landing = $this->get('/'.$slug)->assertOk();
        $faq = $this->get('/faq')->assertOk();

        $reused = array_filter(LandingPages::find($slug)->faq, 'is_string');

        $this->assertNotEmpty($reused, "{$slug} reuses no FAQ answers.");

        foreach ($reused as $id) {
            $entry = Faq::find($id);

            $landing->assertSee($entry['question'], false)->assertSee($entry['answer'], false);
            $faq->assertSee($entry['answer'], false);
        }
    }

    /**
     * A question this page asks of its own must not be one the FAQ page
     * already answers, or the two answers drift apart.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_reuses_a_question_rather_than_rewording_it(string $slug): void
    {
        $existing = collect(Faq::questions());

        foreach (array_filter(LandingPages::find($slug)->faq, 'is_array') as $entry) {
            $this->assertNull($existing->firstWhere('id', $entry['id']), "{$slug} redefines the FAQ entry {$entry['id']}.");
            $this->assertNull($existing->firstWhere('question', $entry['question']), "{$slug} rewords an existing FAQ question.");
        }
    }

    #[DataProvider('draftedPageProvider')]
    public function test_every_question_on_a_drafted_page_is_addressable(string $slug): void
    {
        $this->publishLandingPage($slug);

        $response = $this->get('/'.$slug)->assertOk();

        foreach (LandingPages::find($slug)->faq() as $entry) {
            $response->assertSee('id="'.$entry['id'].'"', false);
        }
    }

    /**
     * Every price a page states is the configured one, in the copy and in
     * the markup a machine quotes.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_a_price_change_in_config_reaches_the_copy_and_the_markup(string $slug): void
    {
        config([
            'subscription.plans.monthly.price' => 7.50,
            'subscription.plans.yearly.price' => 62,
            'subscription.trial_days' => 21,
            'subscription.grace_days' => 4,
            'subscription.quotas.dynamic' => 11,
        ]);

        $this->publishLandingPage($slug);

        $response = $this->get('/'.$slug)->assertOk();

        $response
            ->assertSee('$7.50')
            ->assertSee('$62')
            ->assertSee(SubscriptionPrice::saving(Plan::Yearly))
            ->assertSee('21-day free trial')
            ->assertSee('4 days')
            ->assertSee('11 dynamic codes')
            ->assertDontSee('$49')
            ->assertDontSee('$5.90')
            ->assertDontSee('7-day')
            ->assertDontSee('7 days');

        $offers = collect($this->node($this->graphFrom($response), 'SoftwareApplication')['offers']);

        $this->assertNotNull($offers->firstWhere('price', '7.50'));
        $this->assertNotNull($offers->firstWhere('price', '62.00'));
    }

    /**
     * A page for a case that needs many codes says how many a subscription
     * covers and how to get more, and both follow config: the Quota, and the
     * address the larger-quota offer sends people to.
     */
    #[DataProvider('pageThatOutgrowsTheQuotaProvider')]
    public function test_a_page_that_needs_many_codes_states_the_quota_and_the_way_past_it(string $slug): void
    {
        config([
            'subscription.quotas.dynamic' => 13,
            'site.support_email' => 'codes@example.test',
        ]);

        $this->publishLandingPage($slug);

        $this->assertContains('more-codes', LandingPages::find($slug)->faq, "{$slug} does not make the larger-quota offer.");

        $html = $this->get('/'.$slug)->assertOk()->getContent();

        $start = strpos($html, '<div class="eq-prose">');
        $end = strpos($html, '<div class="eq-table-scroll">', $start);
        $ownCopy = preg_replace('~\s+~', ' ', strip_tags(substr($html, $start, $end - $start)));

        $this->assertStringContainsString('13 dynamic codes', $ownCopy, "{$slug} does not state the Quota in its own copy.");
        $this->assertStringContainsString('more than 13 dynamic codes', $html);
        $this->assertStringContainsString('mailto:codes@example.test', $html);
        $this->assertStringNotContainsString('5 dynamic', strip_tags($html));
    }

    /**
     * A review link never changes, so the honest answer on this page is the
     * free one, and it is the first thing the page says.
     */
    public function test_the_google_review_page_answers_with_a_static_code(): void
    {
        $this->publishLandingPage('google-review-qr-code');

        $html = $this->get('/google-review-qr-code')->assertOk()->getContent();

        $this->assertSame(1, preg_match('~<p class="eq-lead">(.*?)</p>~s', $html, $lead), 'The page has no answer under its H1.');

        $this->assertStringContainsString('static', strtolower($lead[1]));
        $this->assertStringContainsString('free', strtolower($lead[1]));
    }

    /**
     * The honesty rule: a page that sells dynamic codes says what happens
     * when the account lapses, and that static codes need nothing from us.
     */
    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_is_honest_about_what_happens_when_an_account_lapses(string $slug): void
    {
        $this->publishLandingPage($slug);

        $this->get('/'.$slug)
            ->assertOk()
            ->assertSee('stop resolving')
            ->assertSee(config('subscription.grace_days').' days')
            ->assertSee('Nothing is deleted')
            ->assertSee('the moment you subscribe again')
            ->assertSee('as long as the page they point to');
    }

    /**
     * The searcher's word, quoted only where the searcher used it. None of
     * these pages is the page for that search.
     */
    #[DataProvider('draftedPageNotAboutExpiryProvider')]
    public function test_a_drafted_page_does_not_call_a_lapsed_code_expired(string $slug): void
    {
        $this->publishLandingPage($slug);

        $html = $this->get('/'.$slug)->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('expired', strip_tags($html));
    }

    /**
     * The page for that search quotes the word in its title and its H1, so
     * the searcher knows they are in the right place, and then speaks of a
     * code that stopped resolving, as every other page does. The markup
     * names the page by its H1, so it quotes the word too.
     */
    public function test_the_expired_page_quotes_the_searcher_and_then_drops_the_word(): void
    {
        $page = LandingPages::find('qr-code-expired');

        $this->assertStringContainsStringIgnoringCase('expired', $page->title);
        $this->assertStringContainsStringIgnoringCase('expired', $page->h1);

        $this->publishLandingPage($page->slug);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();
        $html = preg_replace([
            '~<title>.*?</title>~s',
            '~<h1 class="eq-h1">.*?</h1>~s',
            '~<script type="application/ld\+json">.*?</script>~s',
        ], '', $html);

        $this->assertStringNotContainsStringIgnoringCase('expired', strip_tags($html));
    }

    /**
     * Each page is written from scratch for its case, so no paragraph of one
     * page's own copy turns up on another. The two pages for a dead code in
     * particular must not read as one page published twice.
     */
    public function test_the_drafted_pages_are_not_one_template_with_the_nouns_swapped(): void
    {
        $paragraphs = collect(self::draftedPageProvider())
            ->map(fn (array $case): string => file_get_contents(resource_path('views/landing/'.$case[0].'.blade.php')))
            ->map(function (string $source): array {
                preg_match_all('~<p>(.*?)</p>~s', $source, $matches);

                return array_map(fn (string $paragraph): string => preg_replace('~\s+~', ' ', trim(strip_tags($paragraph))), $matches[1]);
            });

        $all = $paragraphs->flatten();

        $this->assertNotEmpty($all);
        $this->assertSame($all->count(), $all->unique()->count(), 'Two drafted pages share a paragraph.');
    }

    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_embeds_the_static_generator_as_itself(string $slug): void
    {
        $this->publishLandingPage($slug);

        $this->get('/'.$slug)
            ->assertOk()
            ->assertSee('id="static-qr-form"', false)
            ->assertSee('data-page="'.$slug.'"', false)
            ->assertSee("fetch('".route('qr.instant')."'", false)
            ->assertDontSee('Log in to generate a dynamic QR');
    }

    /**
     * Same generator, same endpoint: the code made on a Landing Page is the
     * code the homepage makes for the same link.
     */
    public function test_the_embedded_generator_makes_the_same_code_as_the_homepage(): void
    {
        $this->publishLandingPage(self::HUB);

        $fromHome = $this->from('/')->postJson(route('qr.instant'), ['url' => 'https://example.com/menu'])->assertOk();
        $fromHub = $this->from('/'.self::HUB)->postJson(route('qr.instant'), ['url' => 'https://example.com/menu'])->assertOk();

        $this->assertSame($fromHome->json('png'), $fromHub->json('png'));
        $this->assertSame($fromHome->json('svg'), $fromHub->json('svg'));
    }

    #[DataProvider('draftedPageProvider')]
    public function test_a_drafted_page_sends_strangers_to_the_trial(string $slug): void
    {
        $this->publishLandingPage($slug);

        $this->get('/'.$slug)
            ->assertOk()
            ->assertSee('Start the free trial')
            ->assertSee(route('filament.admin.auth.register'), false)
            ->assertSee('page='.$slug, false);
    }

    public function test_a_published_page_is_in_the_sitemap_and_llms(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/'.self::HUB).'</loc>', false);
        $this->get('/llms.txt')->assertSee('('.url('/'.self::HUB).')', false);
    }

    public function test_an_unpublished_page_is_in_neither(): void
    {
        $sitemap = $this->get('/sitemap.xml')->getContent();
        $llms = $this->get('/llms.txt')->getContent();

        foreach (array_keys(LandingPages::all()) as $slug) {
            $this->assertStringNotContainsString('/'.$slug, $sitemap);
            $this->assertStringNotContainsString('/'.$slug, $llms);
        }
    }

    public function test_the_footer_links_the_hub_only_once_it_is_published(): void
    {
        $this->get('/')->assertOk()->assertDontSee('/'.self::HUB, false);

        $this->publishLandingPage(self::HUB);

        $this->get('/')->assertOk()->assertSee('href="'.url('/'.self::HUB).'"', false);
    }

    /**
     * Related links come from the registry and must skip anything a visitor
     * would land on as a 404.
     */
    public function test_related_links_skip_unpublished_pages(): void
    {
        $this->publishLandingPage(self::HUB);

        $html = $this->get('/'.self::HUB)->assertOk()->getContent();

        foreach (array_keys(LandingPages::all()) as $slug) {
            if ($slug !== self::HUB) {
                $this->assertStringNotContainsString('href="'.url('/'.$slug).'"', $html);
            }
        }
    }

    public function test_related_pages_are_listed_once_published(): void
    {
        $this->publishLandingPage(self::HUB, 'qr-code-stopped-working');

        $related = array_map(fn (LandingPage $page): string => $page->slug, LandingPages::relatedTo(LandingPages::find(self::HUB)));

        $this->assertSame(['qr-code-stopped-working'], $related);
    }

    /**
     * A use-case page links up to three siblings and the hub, never itself
     * and never a page nobody has published.
     */
    public function test_a_sibling_lists_at_most_three_pages_plus_the_hub(): void
    {
        $page = LandingPages::find('restaurant-menu-qr-code');

        $this->publishLandingPage(self::HUB, ...array_keys(LandingPages::all()));

        $related = array_map(fn (LandingPage $related): string => $related->slug, LandingPages::relatedTo($page));

        $this->assertCount(4, $related);
        $this->assertSame(self::HUB, end($related));
        $this->assertNotContains($page->slug, $related);
    }

    public function test_the_faq_markup_takes_any_entries_and_any_url(): void
    {
        $json = json_decode(StructuredData::forFaq(
            [['id' => 'one', 'question' => 'Why?', 'answer' => '<p>Because &amp; so.</p>']],
            'https://example.test/page',
            'A page',
        ), true);

        $faq = $json['@graph'][0];

        $this->assertSame('https://example.test/page#faq', $faq['@id']);
        $this->assertSame('A page', $faq['name']);
        $this->assertSame('https://example.test/page#one', $faq['mainEntity'][0]['@id']);
        $this->assertSame('Because & so.', $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    /**
     * Landing Pages join the crawler list only through the registry, so the
     * sitemap cannot list one the router does not serve.
     */
    public function test_every_public_page_still_resolves_with_a_page_published(): void
    {
        $this->publishLandingPage(self::HUB);

        foreach (PublicPages::all() as $entry) {
            $this->get($entry['url'])->assertOk();
        }
    }

    /**
     * Words a reader reads on the page itself: the answer under the H1 and
     * the prose down to the call to action, leaving out the generator.
     */
    private function bodyWordCount(TestResponse $response): int
    {
        $html = $response->getContent();

        $this->assertSame(1, preg_match('~<p class="eq-lead">(.*?)</p>~s', $html, $lead), 'The page has no answer under its H1.');

        $start = strpos($html, '<div class="eq-prose">');
        $this->assertNotFalse($start, 'The page has no prose.');

        $end = strpos($html, '<div class="eq-result-panel">', $start);
        $this->assertNotFalse($end, 'The page has no call to action after its prose.');

        $prose = substr($html, $start, $end - $start);

        return str_word_count(html_entity_decode(strip_tags($lead[1].' '.$prose)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function graphFrom(TestResponse $response): array
    {
        $response->assertOk();

        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $matches);

        $nodes = [];

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);

            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'A JSON-LD block did not parse: '.json_last_error_msg());

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
