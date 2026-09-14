<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SubscriptionPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoon extends Notification implements ShouldQueue
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
     * The action label names no price. The customer has not chosen a plan at
     * trial time, and a button quoting one states it as the only price there
     * is; the line above it names both.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $days = $notifiable->trialDaysRemaining();
        $dynamicCount = $notifiable->qrCodes()->where('type', 'dynamic')->count();

        $message = (new MailMessage)
            ->subject(sprintf(
                'Your %s trial ends in %d %s',
                config('app.name'),
                $days,
                str('day')->plural($days),
            ))
            ->greeting("Hi {$notifiable->name},")
            ->line(sprintf(
                'Your free trial ends on %s.',
                $notifiable->trial_ends_at->format('j F Y'),
            ));

        $message->line($dynamicCount > 0
            ? sprintf(
                'After that, your %d dynamic QR %s will stop working when scanned until you subscribe.',
                $dynamicCount,
                str('code')->plural($dynamicCount),
            )
            : 'After that, you will need a subscription to create dynamic QR codes.');

        return $message
            ->line('A subscription costs '.SubscriptionPrice::everyPriceTaxIncluded().', and you pick which when you subscribe.')
            ->action('Subscribe', route('filament.admin.pages.billing'))
            ->line('Your static QR codes are free forever and are not affected.');
    }
}
