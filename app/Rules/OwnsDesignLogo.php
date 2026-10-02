<?php

namespace App\Rules;

use App\Support\DesignLogo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Design may reference only a logo its Owner uploaded, or the one the code
 * already has. Without this a crafted request could name another Owner's file
 * and then fetch it through its own code's logo route.
 */
class OwnsDesignLogo implements ValidationRule
{
    public function __construct(public ?int $userId, public ?string $currentPath = null) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = is_array($value) ? ($value['logo']['path'] ?? null) : null;

        if (! is_string($path) || $path === '' || $path === $this->currentPath) {
            return;
        }

        if ($this->userId === null || ! DesignLogo::belongsTo($this->userId, $path)) {
            $fail('That logo was not uploaded by you. Upload it again.');
        }
    }
}
