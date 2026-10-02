<?php

namespace App\Filament\Resources\QrCodeResource\Pages;

use App\Filament\Resources\QrCodeResource;
use App\Filament\Resources\QrCodeResource\Concerns\PreviewsEncodedContent;
use App\Filament\Resources\QrCodeResource\Concerns\StoresDesignLogo;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQrCode extends EditRecord
{
    use PreviewsEncodedContent;
    use StoresDesignLogo;

    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
