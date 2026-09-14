<?php

namespace Tests\Unit;

use App\Enums\BillingInterval;
use Tests\TestCase;

class BillingIntervalTest extends TestCase
{
    public function test_a_period_ends_one_interval_after_it_starts(): void
    {
        $start = now()->setDate(2026, 1, 31)->startOfDay();

        $this->assertTrue(
            BillingInterval::Year->endFrom($start)->equalTo($start->copy()->addYear()),
        );

        $this->assertTrue(
            BillingInterval::Month->endFrom($start)->equalTo($start->copy()->addMonth()),
        );
    }

    public function test_the_start_is_not_mutated(): void
    {
        $start = now()->startOfDay();
        $before = $start->copy();

        BillingInterval::Year->endFrom($start);

        $this->assertTrue($start->equalTo($before));
    }
}
