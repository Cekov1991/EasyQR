<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Support\LandingPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing Pages are registered by looping over a list, at the top level of
 * the site. Every route that was there before must still reach its own page,
 * whichever Landing Pages are published.
 */
class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_existing_page_still_renders_its_own_view(): void
    {
        $this->publishLandingPage(...array_keys(LandingPages::all()));

        $views = [
            '/' => 'home',
            '/pricing' => 'pricing',
            '/faq' => 'faq',
            '/report' => 'report',
            '/terms-and-conditions' => 'terms-and-conditions',
            '/privacy-policy' => 'privacy-policy',
            '/refund-policy' => 'refund-policy',
        ];

        foreach ($views as $path => $view) {
            $this->get($path)->assertOk()->assertViewIs($view);
        }
    }

    public function test_the_crawler_files_still_resolve(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->get('/robots.txt')->assertOk()->assertSee('User-agent: *');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/llms.txt')->assertOk()->assertSee('# '.config('app.name'), false);
    }

    public function test_the_panel_login_still_resolves(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
    }

    public function test_a_short_link_still_resolves(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $qrCode = QrCode::factory()->dynamic()->create();

        $this->get("/q/{$qrCode->short_url}")->assertRedirect($qrCode->qr_content_data['url']);
    }
}
