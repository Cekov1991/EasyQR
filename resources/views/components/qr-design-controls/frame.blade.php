@php($frameText = \App\Support\QrDesignOptions::frameText())

{{-- The frame around the code, and the text on it (a limited length; none for the border frame). --}}
<div class="eq-controls-group" data-qr-section="frame">
    <x-qr-design-controls.options setting="frame" />
    <div class="eq-field" data-frame-text-field hidden>
        <label class="eq-field-label" for="qrc-frame-text">Frame text</label>
        <input type="text" id="qrc-frame-text" class="eq-input" data-frame-text maxlength="{{ $frameText['max'] }}" value="{{ $frameText['default'] }}" autocomplete="off">
    </div>
</div>
