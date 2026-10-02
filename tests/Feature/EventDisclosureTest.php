<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the Privacy Policy says about the counts, checked against what the code
 * actually does.
 *
 * The policy used to state flatly that we run no analytics. Counting anything at
 * all makes a sentence like that false, and a false statement about the site is
 * the category of problem that got the Google Analytics section deleted — see
 * PublicPagesTest::test_a_public_page_does_not_claim_analytics_we_do_not_run.
 * So the disclosure is part of the feature, not paperwork that follows it.
 */
class EventDisclosureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rendered from config rather than written out, so the published period
     * cannot drift from the one `events:prune` enforces.
     */
    public function test_the_policy_states_the_configured_retention_period(): void
    {
        config(['site.event_retention_days' => 45]);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('45 days');
    }

    public function test_the_policy_discloses_that_we_keep_anonymous_counts(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('anonymous counts')
            ->assertSee('never who it happened to');
    }

    /**
     * The two promises the counter is built around. If either stops being true,
     * TrackedEvent has grown something it should not have.
     */
    public function test_the_policy_still_promises_no_profile_and_no_third_party_tracking(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('no third-party tracking')
            ->assertSee('build no profile of you');
    }

    /**
     * The homepage tells people nothing about their code is stored. The count
     * written on that same request is the one thing that could contradict it, so
     * the policy has to say plainly that the address is not in it.
     */
    public function test_the_policy_states_the_qr_address_is_never_counted(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('never', false)
            ->assertSee('address you put into a free QR code is never');
    }

    /**
     * The old absolute claim must not survive alongside the new one — leaving
     * "we run no analytics" on the page while counting things is exactly the
     * contradiction this file exists to prevent.
     */
    public function test_the_policy_no_longer_claims_we_run_no_analytics_at_all(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertDontSee('We run no analytics');
    }

    /**
     * Counts now say which of our pages they happened on. The policy has to
     * say so, and has to say that a page load alone is not counted, which is
     * what keeps the promise not to record which pages you visit true.
     */
    public function test_the_policy_says_a_count_may_name_the_page_it_happened_on(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('which of our own pages it happened on')
            ->assertSee('loading a page is never counted by itself');
    }
}
