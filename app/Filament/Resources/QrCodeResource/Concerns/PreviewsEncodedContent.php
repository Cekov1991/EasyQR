<?php

namespace App\Filament\Resources\QrCodeResource\Concerns;

use App\Models\QrCode;
use Throwable;

/**
 * Lets the Design editor ask the page what the code being made encodes. The
 * formatting rules live on the model and nowhere else, so the preview is
 * computed here rather than rebuilt in the browser.
 */
trait PreviewsEncodedContent
{
    /**
     * The string the preview draws. Empty while the form is not far enough along
     * to encode anything. A Dynamic code encodes its Short URL, reserved when the
     * form opened, and an existing code encodes what it was saved with.
     */
    public function encodedContent(): string
    {
        if ($this->record instanceof QrCode) {
            return $this->record->encodedContent();
        }

        try {
            $state = $this->form->getRawState();

            return (new QrCode([
                'type' => $state['type'] ?? 'static',
                'qr_content_type' => $state['qr_content_type'] ?? 'website',
                'qr_content_data' => is_array($state['qr_content_data'] ?? null) ? $state['qr_content_data'] : [],
                'short_url' => $state['short_url'] ?? null,
            ]))->encodedContent();
        } catch (Throwable) {
            return '';
        }
    }
}
