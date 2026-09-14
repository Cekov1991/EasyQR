<?php

namespace Tests\Feature\Subscription;

use App\Enums\Plan;
use App\Services\AgentaOS\AgentaOsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The link is what a buyer is charged against, so the amount and interval on
 * it must come from the Plan — never from a caller that could pair the
 * monthly price with the yearly interval.
 */
class AgentaOsClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('subscription.currency', 'USD');
        config()->set('subscription.plans.monthly.price', 5.90);
        config()->set('subscription.plans.yearly.price', 49);
        Http::preventStrayRequests();
    }

    /**
     * @return array<string, array{0: Plan, 1: float, 2: string}>
     */
    public static function plans(): array
    {
        return [
            'monthly' => [Plan::Monthly, 5.9, 'month'],
            'yearly' => [Plan::Yearly, 49.0, 'year'],
        ];
    }

    #[DataProvider('plans')]
    public function test_a_payment_link_carries_the_plans_amount_and_interval(Plan $plan, float $amount, string $interval): void
    {
        Http::fake(['*/gateway/payment-links' => Http::response(['id' => 'link_1'])]);

        $client = new AgentaOsClient('sk_test_key', 'https://agentaos.test/api/v1');

        $link = $client->createSubscriptionPaymentLink($plan, 'EasyQRCode '.$plan->label(), 'Keeps codes online.');

        $this->assertSame('link_1', $link['id']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://agentaos.test/api/v1/gateway/payment-links'
            && $request->hasHeader('x-api-key', 'sk_test_key')
            && $request['amount'] === $amount
            && $request['currency'] === 'USD'
            && $request['type'] === 'subscription'
            && $request['billingInterval'] === $interval
            && $request['name'] === 'EasyQRCode '.$plan->label()
            && $request['description'] === 'Keeps codes online.');
    }
}
