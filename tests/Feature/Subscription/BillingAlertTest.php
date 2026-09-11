<?php

namespace Tests\Feature\Subscription;

use App\Jobs\GrantSubscriptionEntitlement;
use App\Jobs\ResolveAgentaOsSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingAlert;
use App\Services\BillingAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingAlertTest extends TestCase
{
    use RefreshDatabase;

    private const OPS = 'ops@easyqr.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('subscription.alert_email', self::OPS);
        config()->set('subscription.alert_throttle_minutes', 60);
    }

    public function test_an_alert_is_emailed_to_the_configured_address(): void
    {
        Notification::fake();

        BillingAlerts::raise('some-kind', 'Something went wrong.', ['session_id' => 'sess_1']);

        Notification::assertSentOnDemand(
            BillingAlert::class,
            fn (BillingAlert $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === self::OPS,
        );
    }

    public function test_repeats_of_the_same_kind_are_suppressed_within_the_window(): void
    {
        Notification::fake();

        BillingAlerts::raise('sync-aborted', 'First.');
        BillingAlerts::raise('sync-aborted', 'Second.');
        BillingAlerts::raise('sync-aborted', 'Third.');

        Notification::assertCount(1);
    }

    public function test_different_kinds_are_throttled_independently(): void
    {
        Notification::fake();

        BillingAlerts::raise('sync-aborted', 'One subsystem.');
        BillingAlerts::raise('payment-unattributed', 'A different subsystem.');

        Notification::assertCount(2);
    }

    public function test_no_email_is_sent_when_no_address_is_configured(): void
    {
        Notification::fake();
        config()->set('subscription.alert_email', null);

        BillingAlerts::raise('some-kind', 'Something went wrong.');

        Notification::assertNothingSent();
    }

    public function test_an_unattributable_payment_raises_an_alert(): void
    {
        Notification::fake();

        // No user 999, and no local row to fall back on.
        GrantSubscriptionEntitlement::dispatchSync([
            'session_id' => 'sess_orphan',
            'currency' => 'USD',
            'metadata' => ['user_id' => '999'],
        ]);

        Notification::assertSentOnDemand(BillingAlert::class);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_a_completion_without_a_session_id_raises_an_alert(): void
    {
        Notification::fake();

        GrantSubscriptionEntitlement::dispatchSync(['metadata' => ['user_id' => '1']]);

        Notification::assertSentOnDemand(BillingAlert::class);
    }

    public function test_exhausting_the_retries_raises_an_alert(): void
    {
        Notification::fake();

        $job = new GrantSubscriptionEntitlement([
            'session_id' => 'sess_dead',
            'metadata' => ['user_id' => '21'],
        ]);

        $job->failed(new \RuntimeException('database has gone away'));

        Notification::assertSentOnDemand(BillingAlert::class);
    }

    public function test_a_failed_checkout_creation_raises_an_alert(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            '*/gateway/sessions' => Http::response(['message' => 'successUrl must be a URL address'], 400),
        ]);

        config()->set('services.agentaos.key', 'sk_test_key');
        config()->set('services.agentaos.payment_links.yearly', 'link_uuid_123');

        $this->actingAs(User::factory()->create())
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertSessionHas('error');

        Notification::assertSentOnDemand(BillingAlert::class);
    }

    public function test_a_rejected_webhook_signature_raises_an_alert(): void
    {
        Notification::fake();
        config()->set('services.agentaos.webhook_secret', 'whsec_test_secret');

        $this->call(
            'POST',
            '/webhooks/agentaos',
            server: ['HTTP_X_AGENTAOS_SIGNATURE' => 't='.time().',v1='.str_repeat('a', 64)],
            content: '{"type":"checkout.session.completed"}',
        )->assertStatus(400);

        Notification::assertSentOnDemand(BillingAlert::class);
    }

    public function test_an_unresolved_subscription_raises_an_alert_naming_the_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'checkout_session_id' => 'sess_unresolved',
            'status' => 'active',
        ]);

        (new ResolveAgentaOsSubscription($subscription))->failed(null);

        Notification::assertSentOnDemand(BillingAlert::class);
    }
}
