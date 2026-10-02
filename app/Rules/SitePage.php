<?php

namespace App\Rules;

use App\Support\LandingPages;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The page a counted event says it happened on: `home`, or the slug of a
 * published Landing Page, and nothing else.
 *
 * Shared by both endpoints that write site events, so the generator and the
 * browser-reported events cannot disagree about which labels exist. Anything
 * else is refused outright rather than stored as "unknown", which keeps the
 * column a closed set — see TrackedEvent's second rule.
 */
class SitePage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, LandingPages::pageLabels(), true)) {
            $fail('Events can only be counted on the homepage or a published Landing Page.');
        }
    }
}
