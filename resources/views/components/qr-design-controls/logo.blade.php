{{--
    The logo controls: pick a local file (read with FileReader, never uploaded),
    its shape, size, padding, a white backing and clear space. The size slider's
    maximum follows the padding, set by the script, so the hidden box never
    passes 25% of the code's width.
--}}
<div class="eq-controls-group" data-qr-section="logo">
    <div class="eq-logo-row">
        <span class="eq-logo-thumb" data-logo-thumb></span>
        <button type="button" class="eq-pill" data-logo-pick><span data-logo-pick-label>Add logo</span></button>
        <button type="button" class="eq-link-button" data-logo-remove hidden>Remove</button>
        <input type="file" data-logo-file accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden tabindex="-1">
    </div>
    <p class="eq-field-note" data-logo-message role="status"></p>

    <div data-logo-settings hidden>
        <x-qr-design-controls.options setting="logoShape" label="Logo shape" :options="['square' => 'Square', 'rounded' => 'Rounded', 'circle' => 'Circle']" />

        <div class="eq-slider">
            <label class="eq-field-label" for="qrc-logo-size">Size</label>
            <input type="range" id="qrc-logo-size" data-logo-range="size" min="10" max="25" step="1" value="20">
            <output class="eq-slider-value" data-logo-value="size" for="qrc-logo-size">20%</output>
        </div>
        <div class="eq-slider">
            <label class="eq-field-label" for="qrc-logo-padding">Padding</label>
            <input type="range" id="qrc-logo-padding" data-logo-range="padding" min="0" max="5" step="0.5" value="2">
            <output class="eq-slider-value" data-logo-value="padding" for="qrc-logo-padding">2%</output>
        </div>
        <label class="eq-check"><input type="checkbox" data-logo-switch="backing" checked> White backing</label>
        <label class="eq-check"><input type="checkbox" data-logo-switch="clearSpace" checked> Clear space behind the logo</label>
    </div>
</div>
