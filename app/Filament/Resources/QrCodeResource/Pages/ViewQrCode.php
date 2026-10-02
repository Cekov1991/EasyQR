<?php

namespace App\Filament\Resources\QrCodeResource\Pages;

use App\Filament\Resources\QrCodeResource;
use App\Filament\Resources\QrCodeResource\Widgets\QrCodeScanChart;
use Filament\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewQrCode extends ViewRecord
{
    protected static string $resource = QrCodeResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(2)->schema([
                Section::make('QR Code')
                    ->schema([
                        ViewEntry::make('downloads')->hiddenLabel()->view('filament.infolists.entries.qr-downloads'),
                    ])
                    ->columnSpan(1),

                Section::make('Details')
                    ->schema([
                        TextEntry::make('name')->weight(FontWeight::Bold),
                        TextEntry::make('type')->badge()->color(
                            fn (string $state): string => match ($state) {
                                'dynamic' => 'success',
                                'static' => 'info',
                            },
                        ),
                        TextEntry::make('qr_content_type')->badge(),
                        TextEntry::make('scan_count')
                            ->label('Total Scans')
                            ->visible(fn ($record) => $record->type !== 'static'),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columnSpan(1),
            ]),

            Section::make('Recent Scans')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('scans_today')->label('Scans Today')->state(fn ($record) => $record->scans()->whereDate('scanned_at', today())->count()),
                        TextEntry::make('scans_week')->label('Scans This Week')->state(
                            fn ($record) => $record
                                ->scans()
                                ->whereBetween('scanned_at', [now()->startOfWeek(), now()->endOfWeek()])
                                ->count(),
                        ),
                        TextEntry::make('unique_countries')->label('Countries')->state(fn ($record) => $record->scans()->distinct('country')->count('country')),
                    ]),
                ])
                ->visible(fn ($record) => $record->type !== 'static'),
        ]);
    }

    protected function getFooterWidgets(): array
    {
        // Only show analytics chart for dynamic QR codes
        if ($this->record->type === 'static') {
            return [];
        }

        return [
            QrCodeScanChart::make([
                'record' => $this->record,
            ]),
        ];
    }

    public function getFooterWidgetsColumns(): int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Action::make('edit')->url(fn () => $this->getResource()::getUrl('edit', ['record' => $this->record]));

        return $actions;
    }
}
