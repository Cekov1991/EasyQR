<?php

namespace App\Providers;

use App\Services\AgentaOS\AgentaOsClient;
use Illuminate\Database\Eloquent\Model;
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
    }
}
