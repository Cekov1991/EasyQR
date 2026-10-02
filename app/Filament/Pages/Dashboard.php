<?php

namespace App\Filament\Pages;

use App\Filament\Resources\QrCodeResource\Widgets\RecentScans;
use App\Filament\Resources\QrCodeResource\Widgets\ScansByCountryChart;
use App\Filament\Resources\QrCodeResource\Widgets\ScansByDeviceChart;
use App\Filament\Resources\QrCodeResource\Widgets\StatsOverview;
use App\Filament\Resources\QrCodeResource\Widgets\TopPerformingQrCodes;
use App\Filament\Widgets\SubscriptionBanner;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionBanner::class,
            StatsOverview::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            TopPerformingQrCodes::class,
            RecentScans::class,
            ScansByCountryChart::class,
            ScansByDeviceChart::class,
        ];
    }
}
