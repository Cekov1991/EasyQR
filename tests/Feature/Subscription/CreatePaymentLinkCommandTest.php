<?php

namespace Tests\Feature\Subscription;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The link this command creates is what the buyer is actually charged against,
 * so the amount and interval it sends are the two numbers that matter most in
 * the whole billing path.
 */
class CreatePaymentLinkCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.agentaos.key', 'sk_test_key');
        config()->set('subscription.currency', 'USD');
        config()->set('subscription.plans.yearly.price', 49);
        Http::preventStrayRequests();
    }

    public function test_the_yearly_link_is_created_with_the_yearly_plans_amount_and_interval(): void
    {
        Http::fake([
            '*/gateway/payment-links' => Http::response([
                'id' => 'link_yearly_123',
                'environment' => 'test',
                'checkoutUrl' => 'https://pay.example/link_yearly_123',
            ]),
        ]);

        $this->artisan('agentaos:create-payment-link')
            ->expectsOutputToContain('AGENTAOS_YEARLY_PAYMENT_LINK_ID=link_yearly_123')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request['amount'] === 49.0
            && $request['currency'] === 'USD'
            && $request['type'] === 'subscription'
            && $request['billingInterval'] === 'year');
    }

    public function test_it_refuses_to_run_without_an_api_key(): void
    {
        config()->set('services.agentaos.key', null);

        $this->artisan('agentaos:create-payment-link')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_a_rejected_creation_fails_and_prints_the_reason(): void
    {
        Http::fake([
            '*/gateway/payment-links' => Http::response(['message' => 'amount must be positive'], 400),
        ]);

        $this->artisan('agentaos:create-payment-link')
            ->expectsOutputToContain('amount must be positive')
            ->assertFailed();
    }
}
