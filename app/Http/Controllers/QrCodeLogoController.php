<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodeLogoController extends Controller
{
    /**
     * A year, because a logo's URL changes whenever its file does (see
     * QrCode::logoUrl()), so a cached copy can never be a stale one.
     */
    private const CACHE_SECONDS = 31536000;

    /**
     * The code's logo bytes, read from the bucket through the Storage API and
     * served from our own origin so the canvas that makes a PNG is never
     * tainted. Only the code's Owner may fetch it.
     */
    public function show(Request $request, QrCode $qrCode): StreamedResponse
    {
        abort_unless($qrCode->user_id === $request->user()->id, 403);

        $path = $qrCode->designLogoPath();

        abort_if($path === null || ! Storage::exists($path), 404);

        return Storage::response($path, null, [
            'Cache-Control' => 'private, max-age='.self::CACHE_SECONDS.', immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
