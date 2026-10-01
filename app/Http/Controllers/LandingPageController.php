<?php

namespace App\Http\Controllers;

use App\Support\LandingPages;
use Illuminate\View\View;

/**
 * Serves a Landing Page. Each published page has its own route, with its slug
 * as a route default, so this is never reached for a slug nobody published.
 */
class LandingPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = LandingPages::find($slug);

        abort_unless($page?->published, 404);

        return view($page->view(), [
            'page' => $page,
            'related' => LandingPages::relatedTo($page),
        ]);
    }
}
