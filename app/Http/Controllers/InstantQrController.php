<?php

namespace App\Http\Controllers;

use App\Enums\TrackedEvent;
use App\Http\Requests\GenerateInstantQrRequest;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class InstantQrController extends Controller
{
    public function index(): View
    {
        return view('home');
    }

    /**
     * Generate a static QR code on the fly.
     *
     * Nothing about the code is persisted: no row holding the URL and no stored
     * image, so it exists only in this response. The one row written is a bare
     * count — see TrackedEvent, which records that a code was generated and
     * nothing whatsoever about which code, or by whom — only which of our own
     * pages it happened on. That distinction is what keeps the homepage's "we
     * never store your code or its link" true.
     */
    public function generate(GenerateInstantQrRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $options = ['size' => 600, 'errorCorrection' => 'M', 'style' => QrCode::DEFAULT_STYLE];

        $png = QrCode::buildGenerator($options + ['format' => 'png'])->generate($validated['url']);
        $svg = QrCode::buildGenerator($options + ['format' => 'svg'])->generate($validated['url']);

        TrackedEvent::StaticQrGenerated->record(context: ['page' => $request->page()]);

        return response()->json([
            'png' => 'data:image/png;base64,'.base64_encode((string) $png),
            'svg' => 'data:image/svg+xml;base64,'.base64_encode((string) $svg),
        ]);
    }
}
