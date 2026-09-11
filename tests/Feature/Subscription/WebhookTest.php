<?php

namespace Tests\Feature\Subscription;

use App\Jobs\GrantSubscriptionEntitlement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AgentaOS\WebhookSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    /**
     * What AgentaOS reports when the resolve job lists subscriptions. Empty by
     * default; fakeRemoteSubscription() fills it. Held as state rather than a
     * second Http::fake() call because merged stubs resolve first-match-wins,
     * so a later fake for the same URL would never be reached.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $remoteSubscriptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.agentaos.webhook_secret', self::SECRET);

        // The queue runs synchronously in tests, so granting entitlement also
        // runs the follow-up resolve job. Without these, that job would make a
        // real outbound call to api.agentaos.ai.
        Http::preventStrayRequests();
        Http::fake([
            '*/gateway/subscriptions*' => fn () => Http::response([
                'items' => $this->remoteSubscriptions,
                'total' => count($this->remoteSubscriptions),
                'hasMore' => false,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sign(array $event, ?int $timestamp = null, ?string $secret = null): array
    {
        $payload = json_encode($event);
        $timestamp ??= time();
        $digest = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::SECRET);

        return [$payload, "t={$timestamp},v1={$digest}"];
    }

    /**
     * @return array<string, mixed>
     */
    private function completionEvent(int $userId, string $sessionId = 'sess_abc', ?string $subscriptionId = null): array
    {
        $metadata = ['user_id' => (string) $userId];

        // AgentaOS adds keys of its own to the metadata we sent, and on a real
        // payment one of them is the id of the subscription just created.
        if ($subscriptionId !== null) {
            $metadata['subscriptionId'] = $subscriptionId;
            $metadata['payer'] = ['name' => 'Buyer', 'email' => 'buyer@example.com'];
        }

        return [
            'id' => 'evt_'.$sessionId,
            'type' => 'checkout.session.completed',
            'data' => [
                'link_id' => 'link_123',
                'session_id' => $sessionId,
                'amount' => '27.00',
                'currency' => 'USD',
                'rail' => 'card',
                'payer_type' => 'human',
                'testnet' => true,
                'metadata' => $metadata,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeRemoteSubscription(array $overrides = []): void
    {
        $this->remoteSubscriptions = [array_merge([
            'id' => 'sub_remote_1',
            'customerEmail' => 'buyer@example.com',
            'status' => 'active',
            'currentPeriodEnd' => now()->addYear()->toIso8601String(),
            'unitAmountMinor' => 2700,
            'currency' => 'USD',
        ], $overrides)];
    }

    /**
     * Drives the real path a buyer takes: the checkout this app opens is the
     * row the webhook later completes, and the Plan travels on that row.
     */
    private function openCheckout(User $user, string $plan, string $sessionId = 'sess_abc'): void
    {
        config()->set('services.agentaos.key', 'sk_test_key');
        config()->set('services.agentaos.payment_links.monthly', 'link_monthly');
        config()->set('services.agentaos.payment_links.yearly', 'link_yearly');

        Http::fake([
            '*/gateway/sessions' => Http::response([
                'session_id' => $sessionId,
                'currency' => 'USD',
                'checkoutUrl' => 'https://app.agentaos.ai/pay/'.$sessionId,
            ], 201),
        ]);

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => $plan])
            ->assertRedirect('https://app.agentaos.ai/pay/'.$sessionId);

        auth()->logout();
    }

    private function postWebhook(string $payload, ?string $signature): TestResponse
    {
        return $this->call(
            'POST',
            '/webhooks/agentaos',
            server: $signature === null ? [] : ['HTTP_X_AGENTAOS_SIGNATURE' => $signature],
            content: $payload,
        );
    }

    public function test_a_correctly_signed_event_is_accepted_and_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload, $signature] = $this->sign($this->completionEvent($user->id));

        $this->postWebhook($payload, $signature)->assertNoContent();

        Queue::assertPushed(GrantSubscriptionEntitlement::class);
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload] = $this->sign($this->completionEvent($user->id));

        $this->postWebhook($payload, null)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_a_forged_signature_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload] = $this->sign($this->completionEvent($user->id), secret: 'whsec_wrong_secret');
        $forged = 't='.time().',v1='.str_repeat('a', 64);

        $this->postWebhook($payload, $forged)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload, $signature] = $this->sign($this->completionEvent($user->id));

        $tampered = str_replace('"27.00"', '"0.01"', $payload);

        $this->postWebhook($tampered, $signature)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_a_replayed_old_signature_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload, $signature] = $this->sign(
            $this->completionEvent($user->id),
            timestamp: time() - 600,
        );

        $this->postWebhook($payload, $signature)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_a_signature_from_the_future_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        [$payload, $signature] = $this->sign(
            $this->completionEvent($user->id),
            timestamp: time() + 600,
        );

        $this->postWebhook($payload, $signature)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_verification_fails_closed_when_no_secret_is_configured(): void
    {
        $this->assertFalse(WebhookSignature::isValid('{}', 't=1,v1=abc', null));
        $this->assertFalse(WebhookSignature::isValid('{}', 't=1,v1=abc', ''));
    }

    public function test_unrelated_event_types_are_acknowledged_but_ignored(): void
    {
        Queue::fake();

        [$payload, $signature] = $this->sign([
            'id' => 'evt_send',
            'type' => 'send.completed',
            'data' => ['transaction_id' => 'tx_1'],
        ]);

        $this->postWebhook($payload, $signature)->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_a_paid_checkout_grants_entitlement(): void
    {
        $user = User::factory()->create();
        $originalEntitlement = $user->entitled_until;

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));

        $this->postWebhook($payload, $signature)->assertNoContent();

        $user->refresh();

        $this->assertTrue($user->isSubscribed());
        $this->assertTrue($user->entitled_until->greaterThan($originalEntitlement));
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'checkout_session_id' => 'sess_abc',
            'status' => 'active',
        ]);
    }

    /**
     * The regression test for the defect the plan work exists to fix. The
     * interval is a property of the plan the buyer chose, not of config: the
     * grant job used to grant one period of the default plan, so a monthly
     * buyer was handed a year that nothing could later shorten, because the
     * entitlement date only moves forward (ADR-0002).
     *
     * No remote subscription matches here, so the resolve job cannot overwrite
     * the provisional clock and it is the provisional clock being asserted.
     */
    public function test_a_monthly_buyer_is_granted_a_month_not_a_year(): void
    {
        config()->set('subscription.default_plan', 'yearly');
        config()->set('subscription.grace_days', 7);

        $user = User::factory()->create();
        $this->openCheckout($user, 'monthly');

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));
        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertTrue(
            $user->fresh()->entitled_until->isSameDay(now()->addMonth()->addDays(7)),
            'A monthly plan must not grant a year of entitlement.',
        );
    }

    public function test_a_yearly_buyer_is_granted_a_year(): void
    {
        config()->set('subscription.default_plan', 'monthly');
        config()->set('subscription.grace_days', 7);

        $user = User::factory()->create();
        $this->openCheckout($user, 'yearly');

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));
        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertTrue($user->fresh()->entitled_until->isSameDay(now()->addYear()->addDays(7)));
    }

    /**
     * A checkout this app never opened — a link shared by hand, say — has no
     * row to read a plan from. Access is still granted, on the default plan,
     * because the money was taken.
     */
    public function test_a_paid_checkout_with_no_local_row_grants_on_the_default_plan(): void
    {
        config()->set('subscription.default_plan', 'monthly');
        config()->set('subscription.grace_days', 7);

        $user = User::factory()->create();

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));
        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertTrue($user->fresh()->entitled_until->isSameDay(now()->addMonth()->addDays(7)));
        $this->assertNull(Subscription::firstWhere('checkout_session_id', 'sess_abc')->plan);
    }

    public function test_a_redelivered_event_grants_entitlement_only_once(): void
    {
        $user = User::factory()->create();

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));
        $this->postWebhook($payload, $signature)->assertNoContent();

        $afterFirst = $user->fresh()->entitled_until;

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));
        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertTrue($afterFirst->equalTo($user->fresh()->entitled_until));
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    public function test_a_payment_that_cannot_be_attributed_grants_nothing(): void
    {
        $user = User::factory()->create();
        $before = $user->entitled_until;

        $event = $this->completionEvent($user->id);
        $event['data']['metadata'] = [];

        [$payload, $signature] = $this->sign($event);

        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertTrue($before->equalTo($user->fresh()->entitled_until));
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_the_subscription_id_in_metadata_is_stored_when_the_payment_lands(): void
    {
        $this->fakeRemoteSubscription();
        $user = User::factory()->create();

        [$payload, $signature] = $this->sign(
            $this->completionEvent($user->id, subscriptionId: 'sub_remote_1'),
        );

        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertDatabaseHas('subscriptions', [
            'checkout_session_id' => 'sess_abc',
            'agentaos_subscription_id' => 'sub_remote_1',
        ]);
    }

    public function test_resolution_matches_on_the_stored_id_even_when_the_buyer_paid_under_another_email(): void
    {
        // The buyer edited their address at the hosted checkout, so the email
        // join AgentaOS offers no longer points at our user.
        $this->fakeRemoteSubscription(['customerEmail' => 'someone.else@example.com']);
        $user = User::factory()->create(['email' => 'ours@example.com']);

        [$payload, $signature] = $this->sign(
            $this->completionEvent($user->id, subscriptionId: 'sub_remote_1'),
        );

        $this->postWebhook($payload, $signature)->assertNoContent();

        $subscription = Subscription::firstWhere('checkout_session_id', 'sess_abc');

        $this->assertSame('sub_remote_1', $subscription->agentaos_subscription_id);
        $this->assertSame(2700, $subscription->unit_amount_minor);
        $this->assertNotNull($subscription->current_period_end);
    }

    public function test_resolution_still_falls_back_to_email_when_metadata_carries_no_id(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $this->fakeRemoteSubscription(['customerEmail' => 'buyer@example.com']);

        [$payload, $signature] = $this->sign($this->completionEvent($user->id));

        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertSame(
            'sub_remote_1',
            Subscription::firstWhere('checkout_session_id', 'sess_abc')->agentaos_subscription_id,
        );
    }

    public function test_an_id_already_held_by_another_row_is_not_duplicated(): void
    {
        $this->fakeRemoteSubscription();
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'checkout_session_id' => 'sess_earlier',
            'agentaos_subscription_id' => 'sub_remote_1',
            'status' => 'active',
        ]);

        [$payload, $signature] = $this->sign(
            $this->completionEvent($user->id, sessionId: 'sess_later', subscriptionId: 'sub_remote_1'),
        );

        $this->postWebhook($payload, $signature)->assertNoContent();

        $this->assertNull(
            Subscription::firstWhere('checkout_session_id', 'sess_later')->agentaos_subscription_id,
        );
        $this->assertSame(1, Subscription::where('agentaos_subscription_id', 'sub_remote_1')->count());
    }
}
