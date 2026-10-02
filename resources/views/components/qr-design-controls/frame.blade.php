{{-- The frame around the code, and the text on it (at most 18 characters; none for the border frame). --}}
<div class="eq-controls-group" data-qr-section="frame">
    <x-qr-design-controls.options setting="frame" label="Frame" :options="['none' => 'None', 'label' => 'Label', 'border' => 'Border', 'badge' => 'Badge']" />
    <div class="eq-field" data-frame-text-field hidden>
        <label class="eq-field-label" for="qrc-frame-text">Frame text</label>
        <input type="text" id="qrc-frame-text" class="eq-input" data-frame-text maxlength="18" value="SCAN ME" autocomplete="off">
    </div>
</div>
