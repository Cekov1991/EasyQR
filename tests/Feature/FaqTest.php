<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Support\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The answers people read before they commit to a print run, and the same
 * answers in the form a search engine quotes.
 *
 * Two failures are worth guarding against here and neither is visible in the
 * product. The first is drift: an answer that states a price, a quota or a
 * trial length in prose while config says something else is a wrong answer
 * given with our authority. The second is the markup silently disagreeing with
 * the page — a stale FAQPage block is quoted back at a stranger who never sees
 * the page it came from.
 */
class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_question_and_answer_is_on_the_page(): void
    {
        $response = $this->get('/faq')->assertOk();

        foreach (Faq::questions() as $entry) {
            $response->assertSee($entry['question'], false);
            $response->assertSee('id="'.$entry['id'].'"', false);
        }
    }

    /**
     * A support reply that points at one answer has to keep pointing at it, so
     * the anchors are hand-written rather than slugged off the question. Two
     * questions sharing one would send half those links to the wrong answer.
     */
    public function test_every_question_has_its_own_anchor(): void
    {
        $ids = array_column(Faq::questions(), 'id');

        $this->assertSame(array_unique($ids), $ids, 'Two questions share an anchor.');
    }

    /**
     * The numbers people act on. Every one is read from config at render time,
     * so changing a quota or a trial length cannot leave this page quoting the
     * old one — which is the whole reason the copy is not written into the view.
     */
    public function test_the_quotas_and_the_trial_come_from_config(): void
    {
        config([
            'subscription.quotas.dynamic' => 11,
            'subscription.quotas.static' => 97,
            'subscription.larger_quota.dynamic' => 40,
            'subscription.trial_days' => 21,
            'subscription.grace_days' => 3,
            'site.scan_retention_months' => 5,
        ]);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('11 dynamic codes', false)
            ->assertSee('97 saved static codes', false)
            ->assertSee('21 days of the full paid service', false)
            ->assertSee('We add 3 days on the end of a', false)
            ->assertSee('5 months', false)
            ->assertDontSee('5 dynamic codes', false);
    }

    /**
     * "Ask us" left the buyer with the most reason to pay holding no price.
     * The answer quotes the larger plan from config, so a change to it cannot
     * leave the FAQ promising the old offer.
     */
    public function test_the_more_codes_answer_prices_the_larger_plan_from_config(): void
    {
        config([
            'subscription.larger_quota.dynamic' => 40,
            'subscription.larger_quota.price' => 120,
        ]);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('40 dynamic codes for $120 a year', false)
            ->assertDontSee('25 dynamic codes for $99 a year', false);
    }

    public function test_both_prices_are_quoted_as_they_are_charged(): void
    {
        config([
            'subscription.plans.monthly.price' => 7.50,
            'subscription.plans.yearly.price' => 62,
        ]);

        $this->get('/faq')
            ->assertOk()
            ->assertSee('$7.50 a month or $62 a year', false);
    }

    /**
     * The content types are read off the model, not listed in the copy. Two of
     * them are commented out there, and a page advertising a kind of code the
     * form does not offer sends someone looking for a menu entry that is not
     * there.
     */
    public function test_the_content_types_are_the_ones_the_form_actually_offers(): void
    {
        $response = $this->get('/faq')->assertOk();

        foreach (QrCode::QR_CONTENT_TYPES as $key => $label) {
            if ($key === 'website') {
                continue;
            }

            $response->assertSee(trim(preg_replace('/^[^\p{L}]+/u', '', $label)), false);
        }

        $response->assertDontSee('Plain Text', false);
        $response->assertDontSee('Location', false);
    }

    public function test_the_page_points_at_the_policies_that_govern_the_answers(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee(url('/refund-policy'), false)
            ->assertSee(url('/privacy-policy'), false)
            ->assertSee(url('/terms-and-conditions'), false)
            ->assertSee(config('site.support_email'), false);
    }

    public function test_the_footer_links_to_it_from_every_public_page(): void
    {
        foreach (['/', '/pricing', '/privacy-policy'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('faq').'"', false);
        }
    }

    /**
     * The markup against the page. Every question marked up, each with an
     * answer that is not empty — a FAQPage carrying a question and no answer is
     * worse than none, because it is indexed as an answered question.
     */
    public function test_the_markup_carries_every_question_with_its_answer(): void
    {
        $faqPage = $this->faqPage();

        $this->assertCount(count(Faq::questions()), $faqPage['mainEntity']);

        foreach ($faqPage['mainEntity'] as $index => $question) {
            $expected = Faq::questions()[$index];

            $this->assertSame('Question', $question['@type']);
            $this->assertSame($expected['question'], $question['name']);
            $this->assertSame(route('faq').'#'.$expected['id'], $question['@id']);
            $this->assertNotEmpty($question['acceptedAnswer']['text']);
        }
    }

    /**
     * Answers are authored as HTML because several of them link to a policy.
     * Markup left in the marked-up text is quoted at a reader verbatim.
     */
    public function test_the_marked_up_answers_are_plain_text(): void
    {
        foreach ($this->faqPage()['mainEntity'] as $question) {
            $text = $question['acceptedAnswer']['text'];

            $this->assertStringNotContainsString('<', $text, "Markup survived into the answer to: {$question['name']}");
            $this->assertStringNotContainsString('&amp;', $text);
            $this->assertSame(trim($text), $text);
        }
    }

    /**
     * The answers are wrapped for whoever edits them. A line break every
     * seventy characters that survives into the markup is quoted back at a
     * reader as though the breaks were meant.
     */
    public function test_the_marked_up_answers_carry_no_line_wrapping(): void
    {
        foreach ($this->faqPage()['mainEntity'] as $question) {
            foreach (explode("\n\n", $question['acceptedAnswer']['text']) as $paragraph) {
                $this->assertStringNotContainsString(
                    "\n",
                    $paragraph,
                    "The answer to \"{$question['name']}\" carries the source's line breaks."
                );
            }
        }
    }

    /**
     * Stripping the tags alone runs the last word of one paragraph into the
     * first of the next, which is how a marked-up answer ends up quoting a
     * sentence nobody wrote.
     */
    public function test_a_multi_paragraph_answer_keeps_its_paragraph_breaks(): void
    {
        $answer = collect($this->faqPage()['mainEntity'])
            ->firstWhere('@id', route('faq').'#static-vs-dynamic');

        $this->assertNotNull($answer);
        $this->assertStringContainsString("\n\n", $answer['acceptedAnswer']['text']);
        $this->assertStringContainsString('A static code carries its destination inside the image.', $answer['acceptedAnswer']['text']);
    }

    /**
     * The FAQPage markup belongs to this page alone. Emitted elsewhere it
     * claims questions are answered on a page that does not answer them.
     */
    public function test_no_other_page_claims_to_be_the_faq(): void
    {
        foreach (['/', '/pricing', '/refund-policy'] as $path) {
            $this->assertNull(
                $this->nodeOfType($this->get($path), 'FAQPage'),
                "{$path} is marked up as the FAQ page."
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function faqPage(): array
    {
        $faqPage = $this->nodeOfType($this->get('/faq'), 'FAQPage');

        $this->assertNotNull($faqPage, 'The FAQ page carries no FAQPage markup.');

        return $faqPage;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nodeOfType(TestResponse $response, string $type): ?array
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

            $nodes = array_merge($nodes, $decoded['@graph']);
        }

        return collect($nodes)->firstWhere('@type', $type);
    }
}
