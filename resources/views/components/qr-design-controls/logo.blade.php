{{--
    The logo controls: pick a local file (read with FileReader, never uploaded),
    its shape, size, padding, a white backing and clear space. The size slider's
    maximum follows the padding, set by the script, so the hidden box never
    passes its limit. Ranges and accepted files come from the Design option data.
--}}
@php($logo = \App\Support\QrDesignOptions::logo())

<div class="eq-controls-group" data-qr-section="logo">
    <div class="eq-logo-row">
        <span class="eq-logo-thumb" data-logo-thumb></span>
        <button type="button" class="eq-pill" data-logo-pick><span data-logo-pick-label>Add logo</span></button>
        <button type="button" class="eq-link-button" data-logo-remove hidden>Remove</button>
        <input type="file" data-logo-file accept="{{ implode(',', $logo['fileTypes']) }}" hidden tabindex="-1">
    </div>
    <p class="eq-field-note" data-logo-message role="status"></p>

    <div data-logo-settings hidden>
        <x-qr-design-controls.options setting="logoShape" />

        <div class="eq-slider">
            <label class="eq-field-label" for="qrc-logo-size">Size</label>
            <input type="range" id="qrc-logo-size" data-logo-range="size" min="{{ $logo['size']['min'] }}" max="{{ $logo['size']['max'] }}" step="{{ $logo['size']['step'] }}" value="{{ $logo['size']['default'] }}">
            <output class="eq-slider-value" data-logo-value="size" for="qrc-logo-size">{{ $logo['size']['default'] }}%</output>
        </div>
        <div class="eq-slider">
            <label class="eq-field-label" for="qrc-logo-padding">Padding</label>
            <input type="range" id="qrc-logo-padding" data-logo-range="padding" min="{{ $logo['padding']['min'] }}" max="{{ $logo['padding']['max'] }}" step="{{ $logo['padding']['step'] }}" value="{{ $logo['padding']['default'] }}">
            <output class="eq-slider-value" data-logo-value="padding" for="qrc-logo-padding">{{ $logo['padding']['default'] }}%</output>
        </div>
        <label class="eq-check"><input type="checkbox" data-logo-switch="backing" checked> White backing</label>
        <label class="eq-check"><input type="checkbox" data-logo-switch="clearSpace" checked> Clear space behind the logo</label>
    </div>
</div>
