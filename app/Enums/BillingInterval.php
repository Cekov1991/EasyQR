<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How often a subscription is billed.
 *
 * Reached only through a Plan, which owns its interval by match. AgentaOS is
 * told the interval when a plan's payment link is created, and the provisional
 * entitlement granted on payment runs one of these from the moment of payment.
 *
 * Those two were declared independently before: config named the interval while
 * GrantSubscriptionEntitlement hardcoded `addYear()`. They agreed, but nothing
 * made them agree — changing the config would have billed one period and
 * granted another, silently and in the customer's favour. See docs/adr/0003.
 */
enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    /**
     * The end of one billing period that begins at $start.
     */
    public function endFrom(CarbonInterface $start): CarbonInterface
    {
        return match ($this) {
            self::Month => $start->copy()->addMonth(),
            self::Year => $start->copy()->addYear(),
        };
    }
}
