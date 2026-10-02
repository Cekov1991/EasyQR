{{--
    The Studio: the live preview beside every Design option as a card. The code is
    drawn by public/js/qr-renderer.js, bound to the shared controls by
    public/js/qr-design-editor.js, and the form state is the Design. A logo is
    uploaded through the page and drawn from our own logo route. The preview
    surround is always light so a code is judged against its own background; the
    controls follow the panel (public/css/qr-design-editor.css).
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="eq-studio"
        wire:ignore
        x-data
        x-init="window.QrDesignEditor ? QrDesignEditor.mount($el, $wire) : window.addEventListener('load', () => QrDesignEditor.mount($el, $wire), { once: true })"
        data-state-path="{{ $getStatePath() }}"
        data-encoded-content="{{ $getEncodedContent() }}"
        @if ($getLogoUrl()) data-logo-url="{{ $getLogoUrl() }}" @endif
    >
        <div class="eq-studio-aside">
            <div class="eq-studio-preview">
                <div class="eq-studio-code" data-studio-code role="img" aria-label="Preview of your QR code"></div>
                <p class="eq-studio-empty" data-studio-empty role="status">Fill in the details above and your code appears here.</p>
            </div>
            <ul class="eq-studio-notices" data-studio-notices role="status" aria-label="Scan check"></ul>
            <p class="eq-studio-hint">High contrast scans best. Test it with your phone at arm's length before you print.</p>
        </div>

        <div class="eq-studio-cards">
            <section class="eq-studio-card" aria-labelledby="studio-looks-title">
                <h3 class="eq-studio-card-title" id="studio-looks-title">Looks</h3>
                <div class="eq-looks" data-studio-looks role="group" aria-labelledby="studio-looks-title"></div>
                <p class="eq-studio-look-name">Look: <strong data-studio-look-name>Rounded</strong></p>
            </section>

            <section class="eq-studio-card" aria-labelledby="studio-shape-title">
                <h3 class="eq-studio-card-title" id="studio-shape-title">Shape</h3>
                <x-qr-design-controls.shapes />
            </section>

            <section class="eq-studio-card" aria-labelledby="studio-colours-title">
                <h3 class="eq-studio-card-title" id="studio-colours-title">Colours</h3>
                <x-qr-design-controls.colours />
            </section>

            <section class="eq-studio-card" aria-labelledby="studio-frame-title">
                <h3 class="eq-studio-card-title" id="studio-frame-title">Frame</h3>
                <x-qr-design-controls.frame />
            </section>

            <section class="eq-studio-card" aria-labelledby="studio-logo-title">
                <h3 class="eq-studio-card-title" id="studio-logo-title">Logo</h3>
                <x-qr-design-controls.logo />
            </section>
        </div>
    </div>
</x-dynamic-component>
