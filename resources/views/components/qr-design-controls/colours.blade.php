@php
    $fields = [
        'codeColor' => ['Code colour', ['#171b19', '#000000', '#1d3b2f', '#233a83', '#6b2f1f', '#0A0F24', '#2C7790']],
        'eyeColor' => ['Eye colour', ['#348FAD', '#171b19', '#3fbf5f', '#233a83', '#a8541f', '#d92d20', '#000000']],
        'bgColor' => ['Background colour', ['#ffffff', '#F4FAFC', '#fdf6ec', '#f1f1f8', '#D4EBF2', '#111111', '#0A0F24']],
    ];
@endphp

{{--
    The colour controls: swatches, a free picker and a hex field for each of the
    code, the eyes and the background. The three controls of one colour stay in
    step; a typed value that is not a colour is flagged and ignored.
--}}
<div class="eq-controls-group" data-qr-section="colours">
    @foreach ($fields as $setting => [$label, $swatches])
        <div class="eq-field" role="group" aria-labelledby="qrc-{{ $setting }}-label">
            <span class="eq-field-label" id="qrc-{{ $setting }}-label">{{ $label }}</span>
            <div class="eq-swatches">
                @foreach ($swatches as $colour)
                    <button type="button" class="eq-swatch" data-colour-swatch="{{ $setting }}" data-value="{{ $colour }}"
                        style="background: {{ $colour }}" aria-label="{{ $label }} {{ $colour }}" aria-pressed="false"></button>
                @endforeach
                <label class="eq-swatch eq-swatch--picker">
                    <span class="eq-visually-hidden">{{ $label }}, pick any colour</span>
                    <input type="color" data-colour-picker="{{ $setting }}" value="{{ $swatches[0] }}">
                </label>
                <input type="text" class="eq-hex" data-colour-hex="{{ $setting }}" value="{{ $swatches[0] }}" maxlength="7"
                    spellcheck="false" autocomplete="off" aria-label="{{ $label }} as hex">
            </div>
        </div>
    @endforeach
</div>
