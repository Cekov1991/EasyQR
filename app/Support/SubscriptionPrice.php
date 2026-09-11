<?php

namespace App\Support;

use App\Enums\BillingInterval;
use App\Enums\Plan;

/**
 * A plan's price as the customer reads it.
 *
 * The same rtrim(rtrim(number_format())) chain was written out four times: on
 * the Filament billing page, in clause 5 of the Terms, and privately inside
 * each of the two trial notifications — which additionally hardcoded the
 * currency symbol and the word "year" around it. Nothing made those four
 * agree; they simply did.
 *
 * Every method that renders an amount takes the Plan it is quoting, and takes
 * it as a required argument. A default would let a call site keep compiling
 * while silently quoting the yearly price in copy that now has to name both,
 * which is the failure this class exists to prevent.
 */
class SubscriptionPrice
{
    public static function currency(): string
    {
        return (string) config('subscription.currency');
    }

    /**
     * "$49" or "$5.90" — a whole amount drops its cents, because a round number
     * reads as a price and "$49.00" reads as a form field.
     *
     * Only an exactly-zero fraction is dropped. The four expressions this method
     * replaced all used rtrim(rtrim($formatted, '0'), '.'), which also eats a
     * significant trailing zero and renders 5.90 as "5.9" — a malformed price.
     * It never surfaced while the only price was a whole number.
     */
    public static function formatted(Plan $plan): string
    {
        return self::render($plan->price());
    }

    /**
     * "$4.09" — the yearly price divided across the months it covers.
     *
     * A smaller number reads as a smaller commitment, which is why the saving
     * is quoted this way. It is also the most misleading figure on the site if
     * it ever appears alone: nobody is charged $4.09 a month. Every caller must
     * put the real charge beside it.
     *
     * Rounded up rather than down, and to the cent. Rounding down would quote a
     * price whose twelve instalments come to less than the amount actually taken.
     *
     * Null for a plan that is not billed yearly. The monthly plan's price is
     * already the monthly figure, and a silent division by a different period
     * is how a plan ends up misquoted.
     */
    public static function monthlyEquivalent(Plan $plan): ?string
    {
        if ($plan->interval() !== BillingInterval::Year) {
            return null;
        }

        return self::render(ceil($plan->price() / 12 * 100) / 100);
    }

    /**
     * "30%" — how much less this plan costs than twelve months of the monthly
     * plan, for the places both prices sit side by side and this one is being
     * recommended.
     *
     * Rounded down to a whole percent, so the saving is never overstated: a
     * buyer who checks the arithmetic must find at least what was promised.
     *
     * Null for a plan that is not billed yearly, including the monthly plan
     * itself, which is the thing the saving is measured against. Null too when
     * a year costs no less than twelve months, because a saving of nothing is
     * a claim that must not be printed.
     */
    public static function saving(Plan $plan): ?string
    {
        if ($plan->interval() !== BillingInterval::Year) {
            return null;
        }

        $twelveMonths = Plan::Monthly->price() * 12;

        if ($twelveMonths <= 0) {
            return null;
        }

        $percent = (int) floor((1 - $plan->price() / $twelveMonths) * 100);

        return $percent > 0 ? $percent.'%' : null;
    }

    /**
     * "$49/year", for the buttons and email actions that need the period in the
     * same breath as the amount.
     */
    public static function perInterval(Plan $plan): string
    {
        return self::formatted($plan).'/'.$plan->interval()->value;
    }

    /**
     * "$5.90/month" — for a page that names one figure with a "from" and leaves
     * the comparison to the pricing page.
     */
    public static function cheapestPerInterval(): string
    {
        $cheapest = collect(Plan::cases())->sortBy(fn (Plan $plan): float => $plan->price())->first();

        return self::perInterval($cheapest);
    }

    /**
     * The dollar sign is not decoration: it is correct only while the currency
     * is USD, so any other currency is suffixed with its code rather than being
     * silently mislabelled as dollars.
     */
    private static function render(float $amount): string
    {
        $formatted = number_format($amount, 2);

        if (str_ends_with($formatted, '.00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return self::currency() === 'USD'
            ? '$'.$formatted
            : $formatted.' '.self::currency();
    }
}
