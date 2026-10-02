<?php

namespace App\Rules;

use App\Support\QrDesignValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Design editor's rule: refuses a Design that is malformed or would not
 * scan, whatever the browser sent. Every reason is reported, not just the first.
 */
class ValidQrDesign implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (QrDesignValidator::errors($value) as $message) {
            $fail($message);
        }
    }
}
