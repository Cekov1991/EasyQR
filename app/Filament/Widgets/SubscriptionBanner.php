<?php

namespace App\Filament\Widgets;

use App\Enums\AccountState;
use App\Enums\Plan;
use App\Support\SubscriptionPrice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SubscriptionBanner extends Widget
{
    protected static string $view = 'filament.widgets.subscription-banner';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    /**
     * Subscribers have nothing to act on, so the banner stays out of their way.
     */
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && ! $user->isSubscribed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $lapsed = $user->accountState() === AccountState::Lapsed;

        return [
            'lapsed' => $lapsed,
            'heading' => $lapsed
                ? 'Your subscription is inactive'
                : sprintf(
                    'Free trial, %d %s left',
                    $user->trialDaysRemaining(),
                    str('day')->plural($user->trialDaysRemaining()),
                ),
            'description' => $lapsed
                ? $this->lapsedDescription($user->qrCodes()->where('type', 'dynamic')->count())
                : 'Your dynamic QR codes are working normally. Subscribe before the trial ends to keep them online.',
            'action' => $lapsed
                ? 'Reactivate subscription'
                : 'Subscribe for '.SubscriptionPrice::perInterval(Plan::Yearly),
        ];
    }

    private function lapsedDescription(int $dynamicCount): string
    {
        if ($dynamicCount === 0) {
            return 'Subscribe to start creating dynamic QR codes. Your static QR codes are free and unaffected.';
        }

        return sprintf(
            '%d dynamic %s no longer %s when scanned. Your static QR codes are unaffected.',
            $dynamicCount,
            str('QR code')->plural($dynamicCount),
            $dynamicCount === 1 ? 'works' : 'work',
        );
    }
}
