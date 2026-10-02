{{--
    One saved code, drawn by public/js/qr-drawing.js with the one renderer. A logo is
    fetched from our own logo route, never another origin. The box is sized here
    so the page does not jump when the drawing arrives.
--}}
<div
    x-data
    x-init="window.QrDrawing && QrDrawing.paint($el)"
    data-qr-content="{{ $content }}"
    data-qr-design="{{ $design }}"
    @if ($logo) data-qr-logo="{{ $logo }}" @endif
    role="img"
    aria-label="QR code for {{ $record->name }}"
    style="width: {{ $size }}px; height: {{ $size }}px;"
    {{ $attributes }}
></div>
