<?php

namespace App\Enums;

use LogicException;

/**
 * What a customer buys: a billing interval and a price under one name.
 *
 * The interval is a property of the plan, resolved by match and never by
 * config. Twice the "which period" question has caused a defect — config named
 * one interval while the grant hardcoded another (18f75cb), then a provisional
 * grant of the configured interval that a shorter real period could never
 * correct, because entitlement dates only move forward (ADR-0002). Putting the
 * interval here means a plan named monthly cannot be configured to bill yearly.
 *
 * The price and the AgentaOS payment link are provider- and market-facing facts
 * that change without a deploy, so they stay in config, keyed per plan.
 * See docs/adr/0003.
 */
enum Plan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * The plan assumed when none was chosen — a webhook for a session with no
     * local row, for instance. Throws on a value this enum does not name, so a
     * mistyped default fails where it is used rather than quietly selling yearly.
     */
    public static function default(): self
    {
        return self::from((string) config('subscription.default_plan'));
    }

    public function interval(): BillingInterval
    {
        return match ($this) {
            self::Monthly => BillingInterval::Month,
            self::Yearly => BillingInterval::Year,
        };
    }

    /**
     * Tax inclusive, in decimal currency units: 5.9 means $5.90.
     *
     * A plan with no configured price is a misconfiguration, and the honest
     * failure is an exception — not a "$0" quoted on the pricing page.
     */
    public function price(): float
    {
        $price = config("subscription.plans.{$this->value}.price");

        if ($price === null || ! is_numeric($price)) {
            throw new LogicException("No price is configured for the {$this->value} plan.");
        }

        return (float) $price;
    }

    /**
     * The id of the AgentaOS payment link that charges this plan's amount on
     * this plan's interval. Null until the link has been created for the
     * environment, which is what the create-payment-link command is for.
     */
    public function paymentLinkId(): ?string
    {
        $id = config("services.agentaos.payment_links.{$this->value}");

        return blank($id) ? null : (string) $id;
    }

    /**
     * The .env variable that holds this plan's payment link id, so the command
     * that creates the link and the alert that fires when it is missing name
     * the same variable.
     */
    public function paymentLinkEnvironmentVariable(): string
    {
        return 'AGENTAOS_'.strtoupper($this->value).'_PAYMENT_LINK_ID';
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
