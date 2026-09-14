<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The renewal charge failed and the card processor is retrying. Nothing has
 * been switched off yet. This is the warning inside the grace window.
 */
class RenewalPaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('We could not renew your '.config('app.name').' subscription')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your renewal payment did not go through, usually an expired or replaced card.')
            ->line('Your QR codes are still working. We will keep retrying for a few days.');

        if ($notifiable->entitled_until !== null) {
            $message->line(sprintf(
                'If the payment still has not cleared by %s, your dynamic QR codes will stop resolving when scanned.',
                $notifiable->entitled_until->format('j F Y'),
            ));
        }

        return $message
            ->action('Update payment details', route('filament.admin.pages.billing'))
            ->line('Your static QR codes are unaffected either way.');
    }
}
