<?php

namespace Tests\Feature\Subscription;

use App\Filament\Pages\Billing;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
