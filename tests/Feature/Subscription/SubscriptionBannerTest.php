<?php

namespace Tests\Feature\Subscription;

use App\Filament\Widgets\SubscriptionBanner;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('subscription.plans.monthly.price', 5.90);
        config()->set('subscription.plans.yearly.price', 49);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * The banner names one figure with a "from" and leaves the comparison to
     * the billing page it links to. It must be the real monthly price, cents
     * intact — the expression this replaced rendered $5.90 as "$5.9".
     */
    public function test_a_trialing_owner_is_invited_to_subscribe_from_the_monthly_price(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SubscriptionBanner::class)
            ->assertOk()
            ->assertSee('Free trial')
            ->assertSee('Subscribe from $5.90/month')
            ->assertDontSee('$5.9/')
            ->assertSeeHtml(route('filament.admin.pages.billing'));
    }

    public function test_a_lapsed_owner_is_invited_to_reactivate(): void
    {
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        Livewire::test(SubscriptionBanner::class)
            ->assertOk()
            ->assertSee('Your subscription is inactive')
            ->assertSee('Reactivate subscription');
    }

    public function test_a_subscriber_does_not_see_the_banner(): void
    {
        $user = User::factory()->create();
        $user->grantEntitlementThrough(now()->addYear());
        $this->actingAs($user->fresh());

        $this->assertFalse(SubscriptionBanner::canView());
    }
}
