<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The promises a buyer reads before deciding, and what a scan records.
 *
 * Each figure is rendered from the config that enforces it, so a changed trial,
 * grace or retention period cannot leave the front of the site making an
 * outdated promise. The lapse line is guarded in both directions: it must say
 * that data is kept, and it must not imply a dynamic code keeps resolving.
 */
class GuaranteesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function pagesCarryingTheGuarantees(): array
    {
        return [
            'homepage' => ['/'],
            'pricing' => ['/pricing'],
        ];
    }

    #[DataProvider('pagesCarryingTheGuarantees')]
    public function test_the_guarantees_are_on_the_page_with_a_linkable_anchor(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee('id="guarantees"', false)
            ->assertSee('id="scan-privacy"', false);
    }

    #[DataProvider('pagesCarryingTheGuarantees')]
    public function test_the_guarantees_quote_their_periods_from_config(string $path): void
    {
        config([
            'subscription.trial_days' => 11,
            'subscription.grace_days' => 13,
            'site.scan_retention_months' => 17,
        ]);

        $this->get($path)
            ->assertSeeText('11-day free trial')
            ->assertSeeText('13 days of grace')
            ->assertSeeText('deleted after 17 months');
    }

    #[DataProvider('pagesCarryingTheGuarantees')]
    public function test_the_guarantees_state_the_refund_window_and_link_the_policy(string $path): void
    {
        $this->get($path)
            ->assertSeeText('14 days to ask for a full refund')
            ->assertSee(url('/refund-policy'), false);
    }

    #[DataProvider('pagesCarryingTheGuarantees')]
    public function test_the_lapse_promise_says_codes_pause_rather_than_keep_working(string $path): void
    {
        $this->get($path)
            ->assertSeeText('stop resolving')
            ->assertSeeText('start again the moment you subscribe');
    }

    #[DataProvider('pagesCarryingTheGuarantees')]
    public function test_the_scan_privacy_block_states_what_is_and_is_not_recorded(string $path): void
    {
        $this->get($path)
            ->assertSeeText('No IP address')
            ->assertSeeText('Static codes record nothing')
            ->assertSee(route('faq').'#scan-data', false);
    }
}
