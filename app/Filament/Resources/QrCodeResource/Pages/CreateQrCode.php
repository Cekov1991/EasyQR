<?php

namespace App\Filament\Resources\QrCodeResource\Pages;

use App\Filament\Resources\QrCodeResource;
use App\Filament\Resources\QrCodeResource\Concerns\PreviewsEncodedContent;
use App\Filament\Resources\QrCodeResource\Concerns\StoresDesignLogo;
use App\Models\QrCode;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateQrCode extends CreateRecord
{
    use PreviewsEncodedContent;
    use StoresDesignLogo;

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
            $this->replaceTakenShortUrl();

            return;
        }

        [$title, $body] = $type === 'dynamic' && $user->isLapsed()
            ? [
                'Subscription required',
                'Dynamic QR codes need an active subscription. Your static QR codes are unaffected.',
            ]
            : [
                'QR code limit reached',
                sprintf(
                    'You have used all %d of your %s QR codes. Delete one to free a slot.',
                    $user->quota()->limitFor($type),
                    $type,
                ),
            ];

        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->persistent()
            ->send();

        $this->halt();
    }

    /**
     * The Short URL was reserved when the form opened, so it can in principle
     * have been taken since. A Dynamic code's pattern is the Short URL, so the
     * Owner is shown the new one and saves again rather than being given a code
     * they did not approve.
     */
    protected function replaceTakenShortUrl(): void
    {
        $shortUrl = $this->data['short_url'] ?? null;

        if (! is_string($shortUrl) || ! QrCode::query()->where('short_url', $shortUrl)->exists()) {
            return;
        }

        $this->data['short_url'] = QrCode::reserveShortUrl();

        Notification::make()
            ->warning()
            ->title('Your code\'s link was taken')
            ->body('Someone else got that link first, so we picked a new one. Check the preview and save again.')
            ->persistent()
            ->send();

        $this->halt();
    }
}
