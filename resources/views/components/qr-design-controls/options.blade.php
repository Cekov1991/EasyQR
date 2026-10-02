@props(['setting', 'label', 'options'])

{{--
    One row of icon buttons for a closed set of choices. Each button states
    whether it is selected through aria-pressed and carries its own visible name,
    so it is operable and announced without the picture. public/js/qr-design-controls.js
    binds to data-setting / data-value and draws the icon into data-icon.
--}}
<div class="eq-field" role="group" aria-labelledby="qrc-{{ $setting }}-label">
    <span class="eq-field-label" id="qrc-{{ $setting }}-label">{{ $label }}</span>
    <div class="eq-options">
        @foreach ($options as $value => $name)
            <button type="button" class="eq-option" data-setting="{{ $setting }}" data-value="{{ $value }}" aria-pressed="false">
                <span class="eq-option-icon" data-icon aria-hidden="true"></span>
                <span class="eq-option-name">{{ $name }}</span>
            </button>
        @endforeach
    </div>
</div>
