<?php

namespace App\Console\Commands;

use App\Enums\Plan;
use App\Services\AgentaOS\AgentaOsClient;
use App\Services\AgentaOS\AgentaOsException;
use Illuminate\Console\Command;

/**
 * Creates a plan's subscription payment link at AgentaOS.
 *
 * Run once per plan per environment. A command rather than dashboard clicks so
 * that test mode and live mode are created identically and reproducibly. The
 * amount and interval are fixed on the link when it is created, so a price
 * change means running this again and swapping the id.
 *
 * Only the yearly link can be created for now; a plan argument follows.
 */
class CreateAgentaOsPaymentLink extends Command
{
    protected $signature = 'agentaos:create-payment-link
                            {--name= : Product name shown at checkout}
                            {--description= : Longer description shown at checkout}';

    protected $description = 'Create the yearly subscription payment link at AgentaOS';

    public function handle(AgentaOsClient $agentaOs): int
    {
        if (blank(config('services.agentaos.key'))) {
            $this->components->error('AGENTAOS_API_KEY is not configured.');

            return self::FAILURE;
        }

        $plan = Plan::Yearly;

        $name = $this->option('name') ?: config('app.name').' '.$plan->label();
        $description = $this->option('description')
            ?: sprintf(
                'Keeps your dynamic QR codes online. Up to %d dynamic and %d static QR codes.',
                config('subscription.quotas.dynamic'),
                config('subscription.quotas.static'),
            );

        $this->components->info(sprintf(
            'Creating a %s %s subscription link…',
            number_format($plan->price(), 2),
            config('subscription.currency'),
        ));

        try {
            $link = $agentaOs->createSubscriptionPaymentLink($plan, $name, $description);
        } catch (AgentaOsException $exception) {
            $this->components->error($exception->getMessage());

            if ($exception->requestId !== null) {
                $this->line("  Request id: {$exception->requestId}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Payment link created.');
        $this->components->twoColumnDetail('Environment', $link['environment'] ?? 'unknown');
        $this->components->twoColumnDetail('Checkout URL', $link['checkoutUrl'] ?? 'none');
        $this->newLine();
        $this->line('Add this to your .env:');
        $this->line("  {$plan->paymentLinkEnvironmentVariable()}={$link['id']}");
        $this->newLine();

        return self::SUCCESS;
    }
}
