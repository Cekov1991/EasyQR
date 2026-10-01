<?php

namespace App\Filament\Resources\QrCodeResource\Pages;

use App\Filament\Resources\QrCodeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateQrCode extends CreateRecord
{
    protected static string $resource = QrCodeResource::class;

    /**
     * The form already hides an unavailable type, but that is presentation.
     * This is the enforcement — a crafted request must not slip past a quota
     * or create a dynamic code for a lapsed account.
     */
    protected function beforeCreate(): void
    {
        $type = $this->data['type'] ?? 'static';
        $user = Auth::user();

        if ($user->quota()->canCreate($type)) {
            return;
        }

        [$title, $body] = match (true) {
            $type === 'dynamic' && $user->isLapsed() => [
                'Subscription required',
                'Dynamic QR codes need an active subscription. Your static QR codes are unaffected.',
            ],
            $type === 'dynamic' => [
                'QR code limit reached',
                QrCodeResource::dynamicLimitReachedMessage($user),
            ],
            default => [
                'QR code limit reached',
                sprintf(
                    'You have used all %d of your %s QR codes. Delete one to free a slot.',
                    $user->quota()->limitFor($type),
                    $type,
                ),
            ],
        };

        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->persistent()
            ->send();

        $this->halt();
    }
}
