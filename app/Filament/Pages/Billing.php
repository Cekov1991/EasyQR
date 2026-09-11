<?php

namespace App\Filament\Pages;

use App\Enums\Plan;
use App\Models\Subscription;
use App\Services\AgentaOS\AgentaOsClient;
use App\Services\AgentaOS\AgentaOsException;
use App\Support\SubscriptionPrice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Billing extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Subscription';

    protected static ?string $title = 'Subscription';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.billing';

    public function getSubscription(): ?Subscription
    {
        return Auth::user()->currentSubscription();
    }

    public function getFormattedPrice(): string
    {
        return SubscriptionPrice::formatted(Plan::Yearly);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->cancelAction(),
        ];
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel subscription')
            ->color('danger')
            ->outlined()
            ->requiresConfirmation()
            ->modalHeading('Cancel your subscription?')
            ->modalDescription(
                'You keep full access until the end of the period you have already paid for. '
                .'No refund is issued, and nothing is charged again after that.'
            )
            ->modalSubmitActionLabel('Yes, cancel it')
            ->visible(fn (): bool => $this->canCancel())
            ->action(fn () => $this->cancel());
    }

    private function canCancel(): bool
    {
        $subscription = $this->getSubscription();

        return $subscription !== null
            && $subscription->agentaos_subscription_id !== null
            && ! $subscription->cancel_at_period_end;
    }

    private function cancel(): void
    {
        $subscription = $this->getSubscription();

        if ($subscription?->agentaos_subscription_id === null) {
            return;
        }

        try {
            $result = app(AgentaOsClient::class)
                ->cancelSubscription($subscription->agentaos_subscription_id, atPeriodEnd: true);
        } catch (AgentaOsException $exception) {
            Log::error('Could not cancel an AgentaOS subscription.', [
                'subscription_id' => $subscription->getKey(),
                'status' => $exception->status,
                'request_id' => $exception->requestId,
            ]);

            Notification::make()
                ->danger()
                ->title('We could not cancel the subscription')
                ->body('Please try again in a moment, or contact support if it keeps failing.')
                ->send();

            return;
        }

        // Entitlement is untouched: cancellation stops renewals, it does not
        // revoke the period already paid for.
        $subscription->update([
            'status' => $result['status'] ?? $subscription->status,
            'cancel_at_period_end' => $result['cancelAtPeriodEnd'] ?? true,
        ]);

        Notification::make()
            ->success()
            ->title('Subscription cancelled')
            ->body($subscription->current_period_end !== null
                ? 'Your QR codes keep working until '.$subscription->current_period_end->format('j F Y').'.'
                : 'Your QR codes keep working until the end of the period you have paid for.')
            ->send();
    }
}
