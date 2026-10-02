<?php

namespace Tests\Feature;

use App\Enums\TrackedEvent;
use App\Models\SiteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The boundary that makes site_events a counter rather than analytics.
 *
 * Section 2 of the Privacy Policy says we build no profile of the pages you
 * visit. A single identifier column on this table would make that false, and it
 * would be a tempting column to add: knowing whether the same person who saw the
 * offer went on to subscribe is genuinely useful. This file is what stops a
 * future change from adding it in a hurry.
 *
 * Written in the shape of ScanPrivacyTest, for the same reason — a promise in a
 * legal document that nothing enforces is not a promise.
 */
class EventPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The columns that would turn a count into a profile. Named individually so
     * a failure says which one came back.
     *
     * @return array<int, array{0: string}>
     */
    public static function forbiddenColumnProvider(): array
    {
        return [
            ['user_id'],
            ['session_id'],
            ['ip_address'],
            ['user_agent'],
            ['referer'],
            ['fingerprint'],
        ];
    }

    #[DataProvider('forbiddenColumnProvider')]
    public function test_the_events_table_has_no_column_that_could_identify_anyone(string $column): void
    {
        $this->assertFalse(
            Schema::hasColumn('site_events', $column),
            "site_events must not have a `{$column}` column. This table counts occurrences; "
            .'it must never be able to say who they belonged to.',
        );
    }

    /**
     * The guard in the other direction from the column checks: asserts against
     * every stored attribute rather than named columns, so a value smuggled in
     * under a different name still fails.
     */
    public function test_a_recorded_event_stores_nothing_about_the_visitor(): void
    {
        $ip = '203.0.113.9';
        $agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $agent])
            ->postJson(route('events.log'), ['event' => TrackedEvent::StaticQrGenerated->value]);

        $stored = json_encode(SiteEvent::query()->sole()->getAttributes());

        $this->assertStringNotContainsString($ip, $stored);
        $this->assertStringNotContainsString($agent, $stored);
    }

    /**
     * The same guarantee on the public endpoint, which is the one path where a
     * stranger causes the row to be written. The request has their address and
     * their user agent in hand and must put neither on the row.
     */
    public function test_an_event_reported_by_a_browser_stores_nothing_about_the_visitor(): void
    {
        $ip = '198.51.100.7';
        $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)';

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $agent])
            ->postJson(route('events.log'), [
                'event' => TrackedEvent::QrDownloaded->value,
                'format' => 'png',
            ])
            ->assertNoContent();

        $stored = json_encode(SiteEvent::query()->sole()->getAttributes());

        $this->assertStringNotContainsString($ip, (string) $stored);
        $this->assertStringNotContainsString($agent, (string) $stored);
    }

    /**
     * The URL a visitor typed is theirs. The homepage tells them we never store
     * their code or its link, and the count of a code being drawn is the one
     * thing that could quietly contradict it: it is sent by the page, so it
     * must not be able to carry the link even if a caller tries.
     */
    public function test_a_drawn_code_never_records_the_url_it_encoded(): void
    {
        $url = 'https://example.com/a-private-menu-nobody-should-see';

        $this->postJson(route('events.log'), [
            'event' => TrackedEvent::StaticQrGenerated->value,
            'url' => $url,
            'link' => $url,
            'context' => ['url' => $url],
        ])->assertNoContent();

        $this->assertStringNotContainsString(
            'a-private-menu-nobody-should-see',
            json_encode(SiteEvent::query()->sole()->getAttributes()),
        );
    }

    /**
     * The homepage used to say flatly "Nothing is stored". Counting a generation
     * writes a row on that very request, which made an absolute claim imprecise
     * even though nothing about the code itself is kept. The copy now promises
     * the specific thing that is actually true, and this holds it there.
     */
    public function test_the_homepage_promises_only_what_the_counter_leaves_true(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('We never store your code or its link')
            ->assertDontSee('Nothing is stored');
    }

    /**
     * Page views are the highest-volume row available and the only candidate with
     * no action behind them. They are also exactly what the Privacy Policy
     * disclaims, so the enum must not learn to count them.
     */
    public function test_no_event_counts_a_page_view(): void
    {
        foreach (TrackedEvent::cases() as $event) {
            $this->assertStringNotContainsString('page_view', $event->value);
            $this->assertStringNotContainsString('visit', $event->value);
        }
    }

    /**
     * Merely loading a public page must write nothing at all.
     */
    public function test_visiting_a_public_page_records_no_event(): void
    {
        foreach (['/', '/pricing', '/privacy-policy'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->assertSame(0, SiteEvent::query()->count());
    }
}
