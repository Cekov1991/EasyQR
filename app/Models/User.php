<?php

namespace App\Models;

use App\Enums\AccountState;
use App\Enums\SignupSource;
use App\Observers\UserObserver;
use App\Services\QrCodeQuota;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Entitlement columns are deliberately absent: they are billing state and
     * must never be settable from request input. `signup_source` and
     * `signup_landing_page` are absent for the same reason: they are derived
     * from allowlisted query parameters, not from anything the registration
     * form posts.
     *
     * Be aware that this list currently protects nothing. AppServiceProvider
     * calls Model::unguard() in boot(), which disables mass-assignment guarding
     * application-wide, so every column here is writable by fill() regardless of
     * what this array says. The columns above are safe because of where they are
     * written, not because of this list. Removing that unguard() call would make
     * this array mean what it claims — StaticOfferTest documents the gap.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'signup_source' => SignupSource::class,
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'entitled_until' => 'datetime',
            'trial_ending_notified_at' => 'datetime',
            'access_ended_notified_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Allow access to admin panel, but email verification will be enforced by Filament
        return true;
    }

    /** @return HasMany<QrCode, $this> */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The subscription that currently governs this account, if any.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->live()->latest('id')->first();
    }

    /**
     * Extend entitlement to cover a paid period, plus the grace window.
     *
     * This may only ever push the date further out. A failed sync, a stale
     * reply, or a subscription AgentaOS cannot find must never shorten it:
     * granting slightly too much access is recoverable, darkening a paying
     * customer's printed QR codes is not. See docs/adr/0002.
     */
    public function grantEntitlementThrough(?CarbonInterface $periodEnd): bool
    {
        if ($periodEnd === null) {
            return false;
        }

        $candidate = $periodEnd->copy()->addDays((int) config('subscription.grace_days'));

        if ($this->entitled_until !== null && $this->entitled_until->greaterThanOrEqualTo($candidate)) {
            return false;
        }

        $this->entitled_until = $candidate;

        // Re-arm the "your codes are offline" email, so a future lapse is not
        // swallowed by the timestamp from a previous one.
        if ($candidate->isFuture()) {
            $this->access_ended_notified_at = null;
        }

        $this->save();

        return true;
    }

    public function quota(): QrCodeQuota
    {
        return new QrCodeQuota($this);
    }

    /**
     * Whether this account's dynamic QR codes currently resolve when scanned.
     *
     * Reads a date this application owns rather than asking the payment
     * provider, so an AgentaOS outage can never darken a paying customer's
     * printed codes. See docs/adr/0002.
     */
    public function isEntitled(): bool
    {
        return $this->entitled_until !== null && $this->entitled_until->isFuture();
    }

    /**
     * Entitlement reaching past the trial window can only have been paid for.
     */
    public function isSubscribed(): bool
    {
        return $this->isEntitled()
            && $this->trial_ends_at !== null
            && $this->entitled_until->greaterThan($this->trial_ends_at);
    }

    public function isTrialing(): bool
    {
        return $this->isEntitled() && ! $this->isSubscribed();
    }

    public function isLapsed(): bool
    {
        return ! $this->isEntitled();
    }

    public function accountState(): AccountState
    {
        return match (true) {
            $this->isSubscribed() => AccountState::Subscribed,
            $this->isEntitled() => AccountState::Trialing,
            default => AccountState::Lapsed,
        };
    }

    /**
     * Whole days left on the free trial, floored at zero once it has passed.
     */
    public function trialDaysRemaining(): int
    {
        if ($this->trial_ends_at === null || $this->trial_ends_at->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($this->trial_ends_at, absolute: true));
    }
}
