<?php

namespace App\Models;

use App\Enums\TrackedEvent;
use Database\Factories\SiteEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One counted occurrence of a TrackedEvent.
 *
 * Rows are written through TrackedEvent::record() rather than constructed here,
 * so the enum stays the only vocabulary of what may be counted. There is no
 * relationship to a user on purpose — see the enum's docblock.
 *
 * `timestamps()` is absent from the table: `occurred_at` is the only time that
 * means anything, and created_at would have been a second copy of it.
 */
class SiteEvent extends Model
{
    /** @use HasFactory<SiteEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'variant',
        'context',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => TrackedEvent::class,
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @param  Builder<$this>  $query */
    public function scopeNamed(Builder $query, TrackedEvent $event): Builder
    {
        return $query->where('name', $event->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeSince(Builder $query, \DateTimeInterface $moment): Builder
    {
        return $query->where('occurred_at', '>=', $moment);
    }

    /**
     * Events labelled with one page, or with none (null) for rows written
     * before the label existed.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnPage(Builder $query, ?string $page): Builder
    {
        return $page === null
            ? $query->whereNull('context->page')
            : $query->where('context->page', $page);
    }

    /** @param  Builder<$this>  $query */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('occurred_at', today());
    }
}
