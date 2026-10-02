<?php

namespace App\Filament\Resources\QrCodeResource\Concerns;

use App\Support\DesignLogo;
use App\Support\QrDesignOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Lets the Design editor put a logo on the bucket. The browser uploads the
 * picture to `designLogoUpload` with Livewire's own uploader, then asks the page
 * to store it; the answer is the path the Design should reference. A logo the
 * Owner replaces or removes is deleted when the code is saved (see QrCode).
 */
trait StoresDesignLogo
{
    /**
     * @var TemporaryUploadedFile|null
     */
    public $designLogoUpload = null;

    /**
     * Squares the uploaded picture and stores it under the Owner's directory.
     *
     * @return array{path: string}|array{error: string}
     */
    public function storeDesignLogo(): array
    {
        $upload = $this->designLogoUpload;
        $this->designLogoUpload = null;

        if (! $upload instanceof TemporaryUploadedFile) {
            return ['error' => 'Choose a picture to upload.'];
        }

        $logo = QrDesignOptions::logo();
        $validator = Validator::make(
            ['logo' => $upload],
            ['logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:'.intdiv($logo['maxBytes'], 1024)]],
            [
                'logo.mimes' => 'Choose a PNG, JPG or WebP picture.',
                'logo.max' => 'That picture is over '.intdiv($logo['maxBytes'], 1048576).' MB. Choose a smaller one.',
                'logo.file' => 'Choose a PNG, JPG or WebP picture.',
            ],
        );

        if ($validator->fails()) {
            $upload->delete();

            return ['error' => $validator->errors()->first()];
        }

        try {
            return ['path' => DesignLogo::store($upload, (int) Auth::id())];
        } catch (Throwable) {
            return ['error' => 'That file could not be read. Choose a PNG, JPG or WebP picture.'];
        } finally {
            $upload->delete();
        }
    }
}
