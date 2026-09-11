<?php

namespace App\Services\AgentaOS;

use App\Enums\Plan;
use App\Models\User;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper over the AgentaOS REST API.
 *
 * Amounts on create calls are decimal currency units (27 means $27). The one
 * exception in this API is `unitAmountMinor` on a Subscription, which is
 * integer minor units — it is read, never sent.
 */
class AgentaOsClient
{
    /** AgentaOS caps list endpoints at 100 items per page. */
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * Creates the payment link for one plan. Run once per plan per environment;
     * the returned id belongs in that plan's AGENTAOS_*_PAYMENT_LINK_ID.
     *
     * The amount and interval are fixed on the link at creation. Changing the
     * plan's configured price afterwards changes what the site displays, not
     * what this link charges — the link has to be recreated.
     *
     * @return array<string, mixed>
     */
    public function createSubscriptionPaymentLink(Plan $plan, string $name, string $description): array
    {
        return $this->send('post', '/gateway/payment-links', [
            'amount' => $plan->price(),
            'currency' => config('subscription.currency'),
            'name' => $name,
            'description' => $description,
            'type' => 'subscription',
            'billingInterval' => $plan->interval()->value,
        ]);
    }

    /**
     * Opens a checkout for one specific user against a plan's link.
     *
     * The user id rides in `metadata`, which AgentaOS returns unchanged on the
     * webhook. That is the only way the resulting payment can be attributed —
     * the Subscription resource itself carries no metadata.
     *
     * @return array<string, mixed>
     */
    public function createCheckout(User $user, string $linkId): array
    {
        return $this->send('post', '/gateway/sessions', [
            'linkId' => $linkId,
            'buyerEmail' => $user->email,
            'buyerName' => $user->name,
            'metadata' => ['user_id' => (string) $user->getKey()],
            'successUrl' => $this->returnUrl('billing.success'),
            'cancelUrl' => $this->returnUrl('billing.cancel'),
        ]);
    }

    /**
     * Builds a return URL from the configured app URL rather than the incoming
     * request host. AgentaOS rejects hosts without a TLD, so a request served
     * over `http://localhost:8000` would otherwise fail validation.
     */
    private function returnUrl(string $routeName): string
    {
        return rtrim((string) config('app.url'), '/').route($routeName, absolute: false);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, hasMore: bool}
     */
    public function listSubscriptions(int $limit = self::PAGE_SIZE, int $offset = 0): array
    {
        /** @var array{items: array<int, array<string, mixed>>, total: int, hasMore: bool} $page */
        $page = $this->send('get', '/gateway/subscriptions', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $page;
    }

    /**
     * Walks every page of subscriptions.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function eachSubscription(): Generator
    {
        $offset = 0;

        do {
            $page = $this->listSubscriptions(self::PAGE_SIZE, $offset);

            foreach ($page['items'] ?? [] as $subscription) {
                yield $subscription;
            }

            $offset += self::PAGE_SIZE;
        } while ($page['hasMore'] ?? false);
    }

    /**
     * Cancels at the end of the paid period by default: the subscriber keeps
     * what they already paid for and no refund is issued.
     *
     * @return array<string, mixed>
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = true): array
    {
        return $this->send('post', "/gateway/subscriptions/{$subscriptionId}/cancel", [
            'atPeriodEnd' => $atPeriodEnd,
        ]);
    }

    /**
     * Every failure leaves here as an AgentaOsException, including the ones
     * that never reached AgentaOS. Callers alert, release or apologise in their
     * catch blocks, and an unreachable host deserves that same handling — not
     * an escaped ConnectionException and a 500.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $uri, array $payload = []): array
    {
        try {
            $response = $this->request()->{$method}($uri, $payload);
        } catch (ConnectionException $exception) {
            throw new AgentaOsException(
                sprintf('%s %s could not reach AgentaOS: %s', strtoupper($method), $uri, $exception->getMessage()),
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw $this->exceptionFrom($response, $method, $uri);
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-api-key' => (string) $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry(2, 300, $this->isWorthRetrying(), throw: false);
    }

    /**
     * A lost connection or a 429/5xx may succeed on a second attempt. A 4xx is
     * a rejected request that will be rejected again, and retrying it only
     * spends another call against the 60-per-60s rate limit.
     *
     * Retrying matters here because none of these calls carry an idempotency
     * key, so a retry of a create that already landed server-side produces a
     * second object. Keeping the predicate narrow keeps that window small.
     *
     * @return callable(Throwable, PendingRequest): bool
     */
    private function isWorthRetrying(): callable
    {
        return function (Throwable $exception): bool {
            if ($exception instanceof ConnectionException) {
                return true;
            }

            if (! $exception instanceof RequestException) {
                return false;
            }

            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        };
    }

    private function exceptionFrom(Response $response, string $method, string $uri): AgentaOsException
    {
        $body = $response->json() ?? [];

        $message = $body['message'] ?? 'AgentaOS request failed';

        if (is_array($message)) {
            $message = implode('; ', $message);
        }

        return new AgentaOsException(
            sprintf('%s %s failed (%d): %s', strtoupper($method), $uri, $response->status(), $message),
            status: $response->status(),
            body: $body,
            requestId: $response->header('x-request-id') ?: ($body['requestId'] ?? null),
        );
    }
}
