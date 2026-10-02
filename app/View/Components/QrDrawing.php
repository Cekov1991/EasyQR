<?php

namespace App\View\Components;

use App\Models\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A saved QR code, drawn in the browser from its Design (ADR-0005). It hands the
 * renderer what to encode and the Design to draw it in, and nothing else: no
 * image is stored or read.
 */
class QrDrawing extends Component
{
    public string $content;

    public string $design;

    public function __construct(public QrCode $record, public int $size = 40)
    {
        $this->content = $record->encodedContent();
        $this->design = (string) json_encode($record->drawingDesign());
    }

    public function render(): View
    {
        return view('components.qr-drawing');
    }
}
