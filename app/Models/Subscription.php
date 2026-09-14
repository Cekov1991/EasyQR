<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A local mirror of an AgentaOS subscription. Never the entitlement gate —
 * `users.entitled_until` is. See docs/adr/0002.
 */
class Subscription extends Model
{
    /**
     * Statuses in which AgentaOS considers the subscription finished. Anything
     * else is either running or being retried, and is left alone.
     */
    public const DEAD_STATUSES = [
        'canceled',
        'incomplete_expired',
        'unpaid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'unit_amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Plan this row was bought under, or the default for a row that never
     * recorded one — a webhook for a checkout this app did not open, say.
     *
     * Named this way on purpose: a `plan()` method beside the `plan` attribute
     * would be read by Eloquent as a relationship.
     */
    public function planOrDefault(): Plan
    {
        return $this->plan ?? Plan::default();
    }

    public function isLive(): bool
    {
        return ! in_array($this->status, self::DEAD_STATUSES, true);
    }

    /**
     * A renewal charge is failing and the processor is retrying it.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::DEAD_STATUSES);
    }

    public function scopeAwaitingRemoteId(Builder $query): Builder
    {
        return $query->whereNull('agentaos_subscription_id');
    }

    /**
     * The per-cycle price in currency units, from AgentaOS's integer minor
     * units — the one field in that API that is not already decimal.
     */
    public function amount(): ?float
    {
        return $this->unit_amount_minor === null
            ? null
            : $this->unit_amount_minor / 100;
    }
}
