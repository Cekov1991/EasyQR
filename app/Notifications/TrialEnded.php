<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SubscriptionPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment dynamic QR codes stop resolving, whether that is the end of
 * an unconverted trial or the end of a cancelled subscription's paid period.
 */
class TrialEnded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The action label names no price, for the same reason as TrialEndingSoon:
     * a button quoting one plan states it as the only price there is, so the
     * line above it names both. That line says nothing about choosing, because
     * this email also reaches a lapsed subscriber who chose a plan already.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $everSubscribed = $notifiable->subscriptions()->whereNotNull('agentaos_subscription_id')->exists();
        $dynamicCount = $notifiable->qrCodes()->where('type', 'dynamic')->count();
        $missedScans = $this->missedScanCount($notifiable);

        $message = (new MailMessage)
            ->subject($dynamicCount > 0
                ? 'Your dynamic QR codes are now offline'
                : 'Your '.config('app.name').' trial has ended')
            ->greeting("Hi {$notifiable->name},")
            ->line($everSubscribed
                ? 'Your subscription has ended.'
                : 'Your free trial has ended.');

        if ($dynamicCount > 0) {
            $message->line(sprintf(
                'Anyone scanning your %d dynamic QR %s now sees a page saying the code is not active.',
                $dynamicCount,
                str('code')->plural($dynamicCount),
            ));
        }

        if ($missedScans > 0) {
            $message->line(sprintf(
                'That has already happened %d %s.',
                $missedScans,
                str('time')->plural($missedScans),
            ));
        }

        return $message
            ->line('A subscription costs '.SubscriptionPrice::everyPriceTaxIncluded().'.')
            ->action('Reactivate', route('filament.admin.pages.billing'))
            ->line('Everything is exactly where you left it, and your static QR codes are unaffected.');
    }

    private function missedScanCount(User $notifiable): int
    {
        return $notifiable->qrCodes()
            ->join('qr_code_scans', 'qr_codes.id', '=', 'qr_code_scans.qr_code_id')
            ->where('qr_code_scans.blocked', true)
            ->count();
    }
}
