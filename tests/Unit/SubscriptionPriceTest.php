<?php

namespace Tests\Unit;

use App\Enums\Plan;
use App\Support\SubscriptionPrice;
use Tests\TestCase;

class SubscriptionPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('subscription.currency', 'USD');
        config()->set('subscription.plans.monthly.price', 5.90);
        config()->set('subscription.plans.yearly.price', 49);
    }

    public function test_a_round_price_is_written_without_trailing_zeros(): void
    {
        $this->assertSame('$49', SubscriptionPrice::formatted(Plan::Yearly));
    }

    /**
     * $5.90 is a shipped price, not a hypothetical. The rtrim chain this class
     * replaced would have rendered it as "$5.9" — a malformed price.
     */
    public function test_a_price_with_real_cents_keeps_them(): void
    {
        $this->assertSame('$5.90', SubscriptionPrice::formatted(Plan::Monthly));

        config()->set('subscription.plans.yearly.price', 27.5);
        $this->assertSame('$27.50', SubscriptionPrice::formatted(Plan::Yearly));
    }

    /**
     * The period beside the amount comes from the plan, and the plan's interval
     * is the one AgentaOS is sent. There is no separate interval to drift.
     */
    public function test_the_period_is_the_plans_own(): void
    {
        $this->assertSame('$49/year', SubscriptionPrice::perInterval(Plan::Yearly));
        $this->assertSame('$5.90/month', SubscriptionPrice::perInterval(Plan::Monthly));
    }

    public function test_the_cheapest_per_interval_is_the_monthly_plan(): void
    {
        $this->assertSame(Plan::Monthly, SubscriptionPrice::cheapest());
        $this->assertSame('$5.90/month', SubscriptionPrice::cheapestPerInterval());
    }

    /**
     * The cheapest plan is whichever costs least per charge, not whichever is
     * named monthly, so a yearly price cut below the monthly one moves the
     * "from" figure rather than leaving it quoting the dearer plan.
     */
    public function test_the_cheapest_plan_follows_the_prices_not_the_names(): void
    {
        config()->set('subscription.plans.yearly.price', 3);

        $this->assertSame(Plan::Yearly, SubscriptionPrice::cheapest());
        $this->assertSame('$3/year', SubscriptionPrice::cheapestPerInterval());
    }

    /**
     * Every plan, cheapest first, each with its own period. Written from
     * Plan::cases() so a plan added later cannot go unquoted in the three
     * sentences a machine reads.
     */
    public function test_every_price_in_prose_names_each_plan_cheapest_first(): void
    {
        $this->assertSame('$5.90 a month or $49 a year', SubscriptionPrice::everyPriceInProse());

        config()->set('subscription.plans.yearly.price', 3);
        $this->assertSame('$3 a year or $5.90 a month', SubscriptionPrice::everyPriceInProse());
    }

    /**
     * "$4.09" — the yearly price spread across the months it covers, rounded up
     * to the cent so twelve instalments never add up to less than is taken.
     */
    public function test_the_yearly_plan_has_a_monthly_equivalent_rounded_up(): void
    {
        $this->assertSame('$4.09', SubscriptionPrice::monthlyEquivalent(Plan::Yearly));

        config()->set('subscription.plans.yearly.price', 100);
        $this->assertSame('$8.34', SubscriptionPrice::monthlyEquivalent(Plan::Yearly));
    }

    /**
     * The monthly price already is the monthly figure; deriving one would be a
     * silent division by a different period.
     */
    public function test_the_monthly_plan_has_no_monthly_equivalent(): void
    {
        $this->assertNull(SubscriptionPrice::monthlyEquivalent(Plan::Monthly));
    }

    /**
     * "30%" — what a year costs against twelve months, rounded down so the
     * saving is never overstated: $49 against $70.80 is 30.8%.
     */
    public function test_the_yearly_saving_against_monthly_is_rounded_down(): void
    {
        $this->assertSame('30%', SubscriptionPrice::saving(Plan::Yearly));

        config()->set('subscription.plans.yearly.price', 35.40);
        $this->assertSame('50%', SubscriptionPrice::saving(Plan::Yearly));
    }

    /**
     * The monthly plan is what the saving is measured against, so it has none
     * of its own to claim.
     */
    public function test_the_monthly_plan_has_no_saving_to_state(): void
    {
        $this->assertNull(SubscriptionPrice::saving(Plan::Monthly));
    }

    public function test_a_year_that_costs_no_less_than_twelve_months_has_no_saving_to_state(): void
    {
        config()->set('subscription.plans.yearly.price', 70.80);
        $this->assertNull(SubscriptionPrice::saving(Plan::Yearly));

        config()->set('subscription.plans.yearly.price', 80);
        $this->assertNull(SubscriptionPrice::saving(Plan::Yearly));
    }

    /**
     * A dollar sign in front of a non-USD amount is a misstatement of price, and
     * price is the one thing AgentaOS reviews us on as merchant of record.
     */
    public function test_a_non_usd_price_is_not_labelled_as_dollars(): void
    {
        config()->set('subscription.plans.yearly.price', 25);
        config()->set('subscription.currency', 'EUR');

        $this->assertSame('25 EUR', SubscriptionPrice::formatted(Plan::Yearly));
        $this->assertStringNotContainsString('$', SubscriptionPrice::formatted(Plan::Yearly));
        $this->assertSame('2.09 EUR', SubscriptionPrice::monthlyEquivalent(Plan::Yearly));
    }

    public function test_the_currency_comes_from_config(): void
    {
        $this->assertSame('USD', SubscriptionPrice::currency());
    }
}
