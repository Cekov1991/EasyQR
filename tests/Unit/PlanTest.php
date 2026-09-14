<?php

namespace Tests\Unit;

use App\Enums\BillingInterval;
use App\Enums\Plan;
use LogicException;
use Tests\TestCase;
use ValueError;

class PlanTest extends TestCase
{
    /**
     * The interval is a property of the plan, not a config value. Two facts
     * drifted apart once before — the period billed and the period granted —
     * and a plan named monthly that bills yearly must not be expressible.
     */
    public function test_each_plan_bills_on_its_own_interval(): void
    {
        $this->assertSame(BillingInterval::Month, Plan::Monthly->interval());
        $this->assertSame(BillingInterval::Year, Plan::Yearly->interval());
    }

    public function test_the_price_comes_from_the_plans_own_config_key(): void
    {
        config()->set('subscription.plans.monthly.price', 5.90);
        config()->set('subscription.plans.yearly.price', 49);

        $this->assertSame(5.90, Plan::Monthly->price());
        $this->assertSame(49.0, Plan::Yearly->price());
    }

    /**
     * A missing price must fail rather than quote $0 — price is the one thing a
     * merchant of record reviews us on.
     */
    public function test_a_plan_with_no_configured_price_throws_rather_than_costing_nothing(): void
    {
        config()->set('subscription.plans.monthly.price', null);

        $this->expectException(LogicException::class);

        Plan::Monthly->price();
    }

    public function test_each_plan_names_the_env_variable_that_holds_its_payment_link(): void
    {
        $this->assertSame('AGENTAOS_MONTHLY_PAYMENT_LINK_ID', Plan::Monthly->paymentLinkEnvironmentVariable());
        $this->assertSame('AGENTAOS_YEARLY_PAYMENT_LINK_ID', Plan::Yearly->paymentLinkEnvironmentVariable());
    }

    public function test_the_payment_link_comes_from_the_plans_own_credential(): void
    {
        config()->set('services.agentaos.payment_links.monthly', 'link_monthly');
        config()->set('services.agentaos.payment_links.yearly', null);

        $this->assertSame('link_monthly', Plan::Monthly->paymentLinkId());
        $this->assertNull(Plan::Yearly->paymentLinkId());
    }

    public function test_a_plan_has_a_customer_facing_label(): void
    {
        $this->assertSame('Monthly', Plan::Monthly->label());
        $this->assertSame('Yearly', Plan::Yearly->label());
    }

    public function test_the_default_plan_is_configured(): void
    {
        config()->set('subscription.default_plan', 'yearly');
        $this->assertSame(Plan::Yearly, Plan::default());

        config()->set('subscription.default_plan', 'monthly');
        $this->assertSame(Plan::Monthly, Plan::default());
    }

    /**
     * Silently falling back to yearly would bill one plan and grant another,
     * so a default this enum does not name has to stop the call it is part of.
     */
    public function test_an_unsupported_default_plan_throws_rather_than_assuming_yearly(): void
    {
        config()->set('subscription.default_plan', 'fortnightly');

        $this->expectException(ValueError::class);

        Plan::default();
    }
}
