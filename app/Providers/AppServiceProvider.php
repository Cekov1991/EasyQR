<?php

namespace App\Providers;

use App\Services\AgentaOS\AgentaOsClient;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentaOsClient::class, function () {
            return new AgentaOsClient(
                config('services.agentaos.key'),
                config('services.agentaos.base_url'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        $this->registerQrRenderer();
    }

    /**
     * The renderer reaches the admin panel as Filament assets, in the order they
     * depend on each other, served from this site and versioned by Filament. The
     * option data is printed ahead of them because the renderer reads it on load.
     */
    private function registerQrRenderer(): void
    {
        FilamentAsset::register([
            ...array_map(
                fn (string $file): Js => Js::make($file, public_path("js/{$file}.js")),
                ['qrcode-generator', 'qr-frame-font', 'qr-renderer', 'qr-drawing', 'qr-download', 'qr-design-controls', 'qr-design-editor'],
            ),
            ...array_map(
                fn (string $file): Css => Css::make($file, public_path("css/{$file}.css")),
                ['qr-design-controls', 'qr-design-editor'],
            ),
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_BEFORE,
            fn (): string => Blade::render('<x-qr-design-controls.data />'),
        );
    }
}
