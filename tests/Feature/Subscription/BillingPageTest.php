<?php

namespace Tests\Feature\Subscription;

use App\Enums\Plan;
use App\Filament\Pages\Billing;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.agentaos.key', 'sk_test_key');
        config()->set('services.agentaos.payment_links.monthly', 'link_monthly');
        config()->set('services.agentaos.payment_links.yearly', 'link_yearly');
        config()->set('subscription.plans.monthly.price', 5.90);
        config()->set('subscription.plans.yearly.price', 49);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
    }

    private function subscribedUser(): User
    {
        $user = User::factory()->create();
        $user->grantEntitlementThrough(now()->addYear());

        Subscription::create([
            'user_id' => $user->id,
            'agentaos_subscription_id' => 'sub_remote_1',
            'status' => 'active',
            'current_period_end' => now()->addYear(),
            'unit_amount_minor' => 2700,
            'currency' => 'USD',
        ]);

        return $user->fresh();
    }

    public function test_a_trialing_user_is_offered_the_subscription(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('Free trial')
            ->assertSee('Subscribe')
            ->assertSee('$49/year');
    }

    /**
     * Both plans, yearly first and recommended with its saving and the
     * effective monthly figure beside the real charge; monthly second. Each is
     * its own form, so what this page posts is exactly what checkout receives.
     */
    public function test_both_plans_are_offered_with_the_yearly_saving_stated(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('$49/year')
            ->assertSee('Save 30%')
            ->assertSee('$4.09')
            ->assertSee('$5.90/month');
    }

    /**
     * End to end from the rendered page: the plans posted are the ones the
     * page put in its forms, in the order it showed them, and each opens a
     * checkout against its own link.
     */
    public function test_either_option_opens_a_checkout_for_the_plan_the_page_posts(): void
    {
        Http::fake([
            '*/gateway/sessions' => Http::response([
                'session_id' => 'sess_xyz',
                'currency' => 'USD',
                'checkoutUrl' => 'https://app.agentaos.ai/pay/sess_xyz',
            ], 201),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $html = Livewire::test(Billing::class)
            ->assertSeeHtml('action="'.route('billing.subscribe').'"')
            ->html();

        preg_match_all('/name="plan" value="([^"]+)"/', $html, $matches);

        $this->assertSame(
            [Plan::Yearly->value, Plan::Monthly->value],
            $matches[1],
            'The page should offer yearly first, then monthly.',
        );

        foreach ($matches[1] as $plan) {
            $this->actingAs($user)
                ->post(route('billing.subscribe'), ['plan' => $plan])
                ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');

            Http::assertSent(fn (Request $request): bool => $request['linkId'] === 'link_'.$plan);
        }
    }

    /**
     * Before the resolve job has learned the real period end, the page can only
     * say how often the subscription renews — and that is the plan's cadence,
     * not a hardcoded "yearly".
     */
    public function test_the_renewal_cadence_falls_back_to_the_subscriptions_own_plan(): void
    {
        $user = User::factory()->create();
        $user->grantEntitlementThrough(now()->addMonth());

        Subscription::create([
            'user_id' => $user->id,
            'checkout_session_id' => 'sess_monthly',
            'plan' => Plan::Monthly,
            'status' => 'active',
            'currency' => 'USD',
        ]);

        $this->actingAs($user->fresh());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('Renews monthly')
            ->assertDontSee('Renews yearly');
    }

    public function test_a_lapsed_user_is_offered_reactivation(): void
    {
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('Inactive')
            ->assertSee('Reactivate');
    }

    public function test_a_subscriber_is_not_asked_to_subscribe_again(): void
    {
        $this->actingAs($this->subscribedUser());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('Subscribed')
            ->assertDontSee('Reactivate');
    }

    /**
     * Entitlement can outlive its row, and a subscriber reading "Renews " with
     * nothing after it reads that as a bug. The default plan's cadence is the
     * honest answer when there is no row to ask.
     */
    public function test_the_renewal_cadence_is_never_blank_for_an_entitled_owner_without_a_row(): void
    {
        config()->set('subscription.default_plan', 'yearly');

        $user = User::factory()->create();
        $user->grantEntitlementThrough(now()->addYear());
        $this->actingAs($user->fresh());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('Renews yearly');
    }

    public function test_quota_usage_is_shown(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Billing::class)
            ->assertOk()
            ->assertSee('0 of 5 used')
            ->assertSee('0 of 50 used');
    }

    public function test_cancelling_stops_renewal_without_revoking_access(): void
    {
        Http::fake([
            '*/gateway/subscriptions/*/cancel' => Http::response([
                'status' => 'active',
                'currentPeriodEnd' => now()->addYear()->toIso8601String(),
                'cancelAtPeriodEnd' => true,
                'effectiveCancelDate' => now()->addYear()->toDateString(),
            ]),
        ]);

        $user = $this->subscribedUser();
        $entitlementBefore = $user->entitled_until;
        $this->actingAs($user);

        Livewire::test(Billing::class)
            ->callAction('cancel')
            ->assertHasNoActionErrors();

        $subscription = $user->currentSubscription();

        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertTrue($entitlementBefore->equalTo($user->fresh()->entitled_until));
        $this->assertTrue($user->fresh()->isEntitled());
    }

    public function test_a_failed_cancellation_changes_nothing_locally(): void
    {
        Http::fake([
            '*/gateway/subscriptions/*/cancel' => Http::response(
                ['statusCode' => 500, 'message' => 'Internal Server Error'],
                500,
            ),
        ]);

        $user = $this->subscribedUser();
        $this->actingAs($user);

        Livewire::test(Billing::class)->callAction('cancel');

        $this->assertFalse($user->currentSubscription()->cancel_at_period_end);
        $this->assertTrue($user->fresh()->isEntitled());
    }

    public function test_there_is_nothing_to_cancel_without_a_subscription(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Billing::class)->assertActionHidden('cancel');

        Http::assertNothingSent();
    }
}
