@php
    $record = $getRecord();
    $fileName = \Illuminate\Support\Str::slug($record->name) ?: 'qr-code';
@endphp

<div
    x-data
    x-init="window.QrDownload && QrDownload.mount($el)"
    data-qr-file-name="{{ $fileName }}"
    class="flex flex-col items-center gap-4"
>
    <x-qr-drawing :record="$record" :size="300" data-qr-source />

    <div class="flex items-center gap-2">
        <label for="qr-png-size" class="text-sm font-medium text-gray-700 dark:text-gray-200">PNG size</label>
        <select id="qr-png-size" data-qr-size class="fi-select-input rounded-lg border-gray-300 text-sm dark:border-white/20 dark:bg-white/5 dark:text-white">
            @foreach (\App\Support\QrDesignOptions::all()['png']['sizes'] as $pixels)
                <option value="{{ $pixels }}" @selected($pixels === \App\Support\QrDesignOptions::all()['png']['default'])>{{ $pixels }} px</option>
            @endforeach
        </select>
    </div>

    <div class="flex flex-wrap justify-center gap-2">
        <x-filament::button type="button" icon="heroicon-o-arrow-down-tray" data-qr-download="png">Download PNG</x-filament::button>
        <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray" data-qr-download="svg">Download SVG</x-filament::button>
        <x-filament::button type="button" color="gray" icon="heroicon-o-archive-box-arrow-down" data-qr-download="zip">Download all formats</x-filament::button>
    </div>

    <p data-qr-status class="text-sm text-gray-500 dark:text-gray-400" role="status"></p>
</div>
