<?php

namespace Tests\Feature\Subscription;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.agentaos.key', 'sk_test_key');
        config()->set('services.agentaos.payment_links.monthly', 'link_monthly_456');
        config()->set('services.agentaos.payment_links.yearly', 'link_uuid_123');
        Http::preventStrayRequests();
    }

    /**
     * Asserts the alert email that went out names the plan, in its subject or
     * any of its lines, so the operator can tell which plan stopped selling.
     */
    private function assertAlertMentions(string $needle): void
    {
        Notification::assertSentOnDemand(
            BillingAlert::class,
            fn (BillingAlert $notification, array $channels, AnonymousNotifiable $notifiable): bool => str_contains(
                json_encode($notification->toMail($notifiable)->toArray()),
                $needle,
            ),
        );
    }

    private function fakeCheckoutCreation(): void
    {
        Http::fake([
            '*/gateway/sessions' => Http::response([
                'id' => 'checkout_uuid',
                'session_id' => 'sess_xyz',
                'currency' => 'USD',
                'checkoutUrl' => 'https://app.agentaos.ai/pay/sess_xyz',
            ], 201),
        ]);
    }

    public function test_a_checkout_that_names_no_plan_is_refused_rather_than_given_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/billing/subscribe')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('plan');

        $this->actingAs($user)
            ->postJson('/billing/subscribe', ['plan' => 'weekly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('plan');

        Http::assertNothingSent();
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_subscribing_redirects_to_the_hosted_checkout(): void
    {
        $this->fakeCheckoutCreation();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');
    }

    public function test_the_checkout_carries_the_user_id_so_the_payment_can_be_attributed(): void
    {
        $this->fakeCheckoutCreation();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/billing/subscribe', ['plan' => 'yearly']);

        Http::assertSent(function (Request $request) use ($user): bool {
            $body = $request->data();

            return str_contains($request->url(), '/gateway/sessions')
                && $request->hasHeader('x-api-key', 'sk_test_key')
                && $body['linkId'] === 'link_uuid_123'
                && $body['buyerEmail'] === $user->email
                && $body['metadata']['user_id'] === (string) $user->id;
        });
    }

    public function test_a_monthly_checkout_opens_against_the_monthly_link_and_records_the_plan(): void
    {
        $this->fakeCheckoutCreation();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'monthly'])
            ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');

        Http::assertSent(fn (Request $request): bool => $request['linkId'] === 'link_monthly_456');

        $this->assertSame(Plan::Monthly, Subscription::firstWhere('checkout_session_id', 'sess_xyz')->plan);
    }

    public function test_the_return_urls_come_from_the_app_url_not_the_request_host(): void
    {
        $this->fakeCheckoutCreation();
        config()->set('app.url', 'https://easyqr.example/');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/billing/subscribe', ['plan' => 'yearly']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $body['successUrl'] === 'https://easyqr.example/billing/success'
                && $body['cancelUrl'] === 'https://easyqr.example/billing/cancel';
        });
    }

    public function test_a_pending_subscription_row_is_recorded_before_the_redirect(): void
    {
        $this->fakeCheckoutCreation();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/billing/subscribe', ['plan' => 'yearly']);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'checkout_session_id' => 'sess_xyz',
            'plan' => 'yearly',
            'status' => 'incomplete',
        ]);
    }

    public function test_paying_does_not_happen_and_nothing_is_recorded_when_the_api_fails(): void
    {
        Notification::fake();
        config()->set('subscription.alert_email', 'ops@easyqr.test');
        Http::fake([
            '*/gateway/sessions' => Http::response(
                ['statusCode' => 400, 'message' => 'amount is required'],
                400,
            ),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'monthly'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertTrue($user->fresh()->isTrialing());
        $this->assertAlertMentions('monthly');

        // A rejected request will be rejected again. Since no call carries an
        // idempotency key, a pointless retry is also a duplicate-object risk.
        Http::assertSentCount(1);
    }

    public function test_a_server_error_is_retried_and_can_still_succeed(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'upstream unavailable'], 503)
            ->push([
                'session_id' => 'sess_xyz',
                'currency' => 'USD',
                'checkoutUrl' => 'https://app.agentaos.ai/pay/sess_xyz',
            ], 201);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');

        Http::assertSentCount(2);
        $this->assertDatabaseHas('subscriptions', ['checkout_session_id' => 'sess_xyz']);
    }

    public function test_rate_limiting_is_retried(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'too many requests'], 429)
            ->push([
                'session_id' => 'sess_xyz',
                'currency' => 'USD',
                'checkoutUrl' => 'https://app.agentaos.ai/pay/sess_xyz',
            ], 201);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');

        Http::assertSentCount(2);
    }

    public function test_an_unreachable_agentaos_apologises_instead_of_erroring(): void
    {
        Notification::fake();
        config()->set('subscription.alert_email', 'ops@easyqr.test');

        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('subscriptions', 0);
        Notification::assertSentOnDemand(BillingAlert::class);
    }

    /**
     * "Nobody can subscribe monthly" is a different incident from "nobody can
     * subscribe", so the alert has to say which link is missing.
     */
    public function test_checkout_is_refused_when_that_plans_payment_link_is_not_configured(): void
    {
        Notification::fake();
        config()->set('subscription.alert_email', 'ops@easyqr.test');
        config()->set('services.agentaos.payment_links.monthly', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'monthly'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertAlertMentions('AGENTAOS_MONTHLY_PAYMENT_LINK_ID');
    }

    public function test_the_other_plan_still_sells_when_one_link_is_missing(): void
    {
        $this->fakeCheckoutCreation();
        config()->set('services.agentaos.payment_links.monthly', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/billing/subscribe', ['plan' => 'yearly'])
            ->assertRedirect('https://app.agentaos.ai/pay/sess_xyz');
    }

    public function test_a_guest_cannot_start_a_checkout(): void
    {
        $this->post('/billing/subscribe', ['plan' => 'yearly'])->assertRedirect('/login');

        Http::assertNothingSent();
    }
}
