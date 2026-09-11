<?php

namespace App\Jobs;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Turns a paid checkout into access.
 *
 * The webhook proves a payment happened and carries our `user_id` in metadata,
 * but not the AgentaOS subscription id — so entitlement is granted immediately
 * on a provisional one-interval clock, and ResolveAgentaOsSubscription follows
 * up to learn the real identity and period end. See docs/adr/0002.
 */
class GrantSubscriptionEntitlement implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $data  The `data` object from checkout.session.completed.
     */
    public function __construct(private readonly array $data) {}

    public function handle(): void
    {
        $sessionId = $this->data['session_id'] ?? null;

        if (blank($sessionId)) {
            BillingAlerts::raise(
                'checkout-without-session-id',
                'An AgentaOS checkout completion arrived without a session_id, so the payment could not be recorded.',
                $this->data,
            );

            return;
        }

        $subscription = Subscription::firstOrNew(['checkout_session_id' => $sessionId]);

        // Deliveries retry, so the same event can arrive more than once.
        if ($subscription->exists && $subscription->status !== 'incomplete') {
            return;
        }

        $user = $this->resolveUser($subscription);

        if ($user === null) {
            BillingAlerts::raise(
                'payment-unattributed',
                'An AgentaOS payment could not be attributed to a user. Money was taken and no access was granted.',
                [
                    'session_id' => $sessionId,
                    'metadata' => $this->data['metadata'] ?? [],
                ],
            );

            return;
        }

        $subscription->fill([
            'user_id' => $user->getKey(),
            'status' => 'active',
            'currency' => $this->data['currency'] ?? config('subscription.currency'),
            'agentaos_subscription_id' => $this->remoteSubscriptionId($subscription),
        ])->save();

        // Provisional: one billing interval from now. The follow-up job
        // replaces this with the period end AgentaOS actually recorded.
        $user->grantEntitlementThrough(Plan::default()->interval()->endFrom(now()));

        ResolveAgentaOsSubscription::dispatch($subscription);
    }

    /**
     * AgentaOS returns our `metadata` unchanged but adds keys of its own, and
     * one of them is the id of the subscription the payment just created.
     *
     * That is undocumented — ADR 0002 was written believing the webhook could
     * not carry it — so it is treated as a windfall rather than a guarantee:
     * when present, nothing downstream has to identify this subscription by
     * email; when absent, the old email match still runs.
     *
     * A renewal that re-fires this event would repeat an id already stored on
     * another row, so the unique column is left alone in that case rather than
     * crash-looping the job on a constraint violation.
     */
    private function remoteSubscriptionId(Subscription $subscription): ?string
    {
        $remoteId = $this->data['metadata']['subscriptionId'] ?? null;

        if (blank($remoteId) || $this->isHeldByAnotherRow($remoteId, $subscription)) {
            return $subscription->agentaos_subscription_id;
        }

        return (string) $remoteId;
    }

    private function isHeldByAnotherRow(string $remoteId, Subscription $subscription): bool
    {
        return Subscription::where('agentaos_subscription_id', $remoteId)
            ->when($subscription->exists, fn ($query) => $query->whereKeyNot($subscription->getKey()))
            ->exists();
    }

    /**
     * Retries are exhausted and no entitlement was granted: the payment is
     * real, the access is not, and only a human can close that gap.
     */
    public function failed(?Throwable $exception): void
    {
        BillingAlerts::raise(
            'entitlement-grant-failed',
            'Granting entitlement for a paid AgentaOS checkout failed after every retry. The customer has paid and has no access.',
            [
                'session_id' => $this->data['session_id'] ?? null,
                'user_id' => $this->data['metadata']['user_id'] ?? null,
                'exception' => $exception?->getMessage(),
            ],
        );
    }

    private function resolveUser(Subscription $subscription): ?User
    {
        $userId = $this->data['metadata']['user_id'] ?? null;

        if (filled($userId) && ($user = User::find($userId)) !== null) {
            return $user;
        }

        // The row created when the checkout was opened is the fallback if
        // metadata ever comes back empty.
        return $subscription->exists ? $subscription->user : null;
    }
}
