<?php

namespace Tests;

use App\Support\LandingPages;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\RouteCollection;
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

    /**
     * Treat Landing Pages as unpublished without editing the registry, so a
     * test can prove a withdrawn page disappears from every place it showed.
     *
     * The routes were registered when the application booted, so the
     * collection is rebuilt without the withdrawn pages' routes.
     */
    protected function unpublishLandingPage(string ...$slugs): void
    {
        config(['site.landing_pages.unpublish' => [...config('site.landing_pages.unpublish', []), ...$slugs]]);

        $withdrawn = array_map(fn (string $slug): string => LandingPages::find($slug)->routeName(), $slugs);
        $routes = new RouteCollection;

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array($route->getName(), $withdrawn, true)) {
                $routes->add($route);
            }
        }

        app('router')->setRoutes($routes);
    }
}
