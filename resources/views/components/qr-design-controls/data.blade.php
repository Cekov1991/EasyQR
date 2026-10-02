{{--
    Hands the Design option data to the browser as the QrDesignOptions global,
    straight from the file the Blade controls are rendered from. Must come before
    qr-renderer.js; the editors put it ahead of their scripts.
--}}
<script>globalThis.QrDesignOptions = @json(\App\Support\QrDesignOptions::all());</script>
