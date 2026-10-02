<?php

namespace Database\Factories;

use App\Enums\TrackedEvent;
use App\Models\SiteEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteEvent>
 */
class SiteEventFactory extends Factory
{
    protected $model = SiteEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => TrackedEvent::StaticQrGenerated,
            'variant' => null,
            'context' => null,
            'occurred_at' => now(),
        ];
    }

    public function named(TrackedEvent $event): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $event,
        ]);
    }

    /**
     * Labelled with the page it happened on.
     */
    public function onPage(string $page): static
    {
        return $this->state(fn (array $attributes): array => [
            'context' => ['page' => $page],
        ]);
    }

    /**
     * Aged past the retention window, for the pruning tests.
     */
    public function occurredDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurred_at' => now()->subDays($days),
        ]);
    }
}
