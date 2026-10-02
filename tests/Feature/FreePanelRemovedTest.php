<?php

namespace Tests\Feature;

use App\Support\PublicPages;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreePanelRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_free_panel_and_everything_under_it_is_gone(): void
    {
        foreach (['/free', '/free/qr-codes', '/free/qr-codes/create'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_only_the_admin_panel_is_registered(): void
    {
        $this->assertSame(['admin'], array_keys(Filament::getPanels()));
    }

    public function test_the_old_guest_handoff_page_is_gone(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())
            ->get('/admin/qr-codes/create-from-session')
            ->assertNotFound();
    }

    public function test_crawlers_are_no_longer_told_about_the_free_panel(): void
    {
        $this->assertNotContains('/free/', PublicPages::closedPaths());
        $this->get('/robots.txt')->assertDontSee('/free/', false);
    }
}
