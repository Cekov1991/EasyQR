<?php

namespace Tests;

use App\Support\LandingPages;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    /**
     * Treat Landing Pages as published without editing the registry, so a
     * page whose copy is still in review can be rendered and asserted on.
     *
     * The routes were registered when the application booted, before this
     * ran, so they are registered again from the registry as it now reads.
     */
    protected function publishLandingPage(string ...$slugs): void
    {
        config(['site.landing_pages.publish' => [...config('site.landing_pages.publish', []), ...$slugs]]);

        Route::middleware('web')->group(fn () => LandingPages::registerRoutes());

        app('router')->getRoutes()->refreshNameLookups();
    }
}
