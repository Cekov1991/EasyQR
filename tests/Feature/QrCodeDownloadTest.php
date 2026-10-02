<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\ListQrCodes;
use App\Filament\Resources\QrCodeResource\Pages\ViewQrCode;
use App\Filament\Resources\QrCodeResource\Widgets\TopPerformingQrCodes;
use App\Models\QrCode;
use App\Models\User;
use App\Support\QrDesignOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard draws every saved code in the browser from its Design (ADR-0005),
 * so what a page owes the browser is the content to encode and the Design to draw
 * it in. The downloads themselves are the browser's, and are proved in tests/js.
 */
class QrCodeDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function inkDesign(): array
    {
        $ink = QrDesignOptions::all()['looks']['ink'];
        unset($ink['label']);

        return [
            'version' => 1,
            ...$ink,
            'frame' => 'badge',
            'frameText' => 'SCAN ME',
            'logo' => null,
        ];
    }

    private function viewPage(QrCode $record): Testable
    {
        return Livewire::actingAs($record->user)->test(ViewQrCode::class, [
            'record' => $record->getKey(),
        ]);
    }

    private function ownerWith(array $attributes = [], bool $dynamic = false): QrCode
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $factory = QrCode::factory()->for($user);

        return ($dynamic ? $factory->dynamic() : $factory)->create($attributes + ['name' => 'Team Poster']);
    }

    private function forgetStoredImage(QrCode $record): void
    {
        Storage::delete($record->qr_code_image);
        DB::table('qr_codes')->where('id', $record->id)->update(['qr_code_image' => null]);
    }

    private function roundedDesign(): array
    {
        $rounded = QrDesignOptions::all()['looks']['rounded'];
        unset($rounded['label']);

        return [
            'version' => 1,
            ...$rounded,
            'frame' => 'none',
            'frameText' => QrDesignOptions::frameText()['default'],
            'logo' => null,
        ];
    }

    public function test_the_view_page_draws_a_static_code_from_its_design_and_content(): void
    {
        $record = $this->ownerWith([
            'options' => ['design' => $this->inkDesign()],
            'qr_content_type' => 'wifi',
            'qr_content_data' => ['ssid' => 'Cafe', 'password' => 'secret', 'security' => 'WPA2'],
        ]);

        $this->viewPage($record)
            ->assertSee($record->formated_content)
            ->assertSee(json_encode($this->inkDesign()));
    }

    public function test_a_dynamic_code_is_drawn_with_its_short_url_redirect(): void
    {
        $record = $this->ownerWith(['options' => ['design' => $this->inkDesign()]], dynamic: true);

        $this->viewPage($record)->assertSee(route('qr.redirect', $record->short_url));
    }

    public function test_a_code_without_a_design_is_drawn_in_the_rounded_look(): void
    {
        $record = $this->ownerWith();

        $this->viewPage($record)->assertSee(json_encode($this->roundedDesign()));
    }

    public function test_a_code_saved_with_an_old_style_still_draws_in_the_rounded_look(): void
    {
        $record = $this->ownerWith(['options' => ['style' => 'dot', 'color' => '#ff0000']]);

        $this->viewPage($record)->assertSee(json_encode($this->roundedDesign()));
    }

    public function test_the_view_page_offers_every_download_size_svg_and_the_zip(): void
    {
        $record = $this->ownerWith();

        $page = $this->viewPage($record);

        foreach ([512, 1024, 2048, 4096] as $size) {
            $page->assertSee($size.' px');
        }

        $page->assertSee('Download PNG')->assertSee('Download SVG')->assertSee('Download all formats');
    }

    public function test_the_view_page_has_no_server_side_downloads(): void
    {
        $this->viewPage($this->ownerWith())
            ->assertActionDoesNotExist('download_all_formats')
            ->assertActionDoesNotExist('download_original');
    }

    public function test_the_view_page_does_not_need_the_stored_image(): void
    {
        $record = $this->ownerWith(['options' => ['design' => $this->inkDesign()]]);
        $this->forgetStoredImage($record);

        $this->viewPage($record)
            ->assertSuccessful()
            ->assertSee($record->formated_content);
    }

    public function test_the_codes_table_draws_each_code_from_its_design(): void
    {
        $designed = $this->ownerWith(['options' => ['design' => $this->inkDesign()]], dynamic: true);
        $plain = QrCode::factory()->for($designed->user)->create(['name' => 'Plain']);
        $this->forgetStoredImage($designed);
        $this->forgetStoredImage($plain);

        Livewire::actingAs($designed->user)->test(ListQrCodes::class)
            ->assertCanSeeTableRecords([$designed, $plain])
            ->assertSee(route('qr.redirect', $designed->short_url))
            ->assertSee(json_encode($this->inkDesign()))
            ->assertSee($plain->formated_content)
            ->assertSee(json_encode($this->roundedDesign()));
    }

    public function test_the_top_performing_widget_draws_each_code_from_its_design(): void
    {
        $designed = $this->ownerWith(['options' => ['design' => $this->inkDesign()]]);
        $plain = QrCode::factory()->for($designed->user)->create(['name' => 'Plain']);
        $this->forgetStoredImage($designed);
        $this->forgetStoredImage($plain);

        Livewire::actingAs($designed->user)->test(TopPerformingQrCodes::class)
            ->assertSee($designed->formated_content)
            ->assertSee(json_encode($this->inkDesign()))
            ->assertSee($plain->formated_content)
            ->assertSee(json_encode($this->roundedDesign()));
    }

    public function test_the_panel_loads_the_renderer_from_this_site_with_a_version(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $page = $this->actingAs($user)->get('/admin/qr-codes')->assertOk()->getContent();

        preg_match_all('/<script[^>]+src="([^"]*qr-renderer[^"]*)"/', $page, $matches);

        $this->assertCount(1, $matches[1], 'The renderer is not loaded exactly once.');
        $this->assertStringStartsWith(url('/'), $matches[1][0]);
        $this->assertMatchesRegularExpression('/\?v=\w+/', $matches[1][0]);
    }

    public function test_the_copies_filament_serves_are_the_current_renderer_files(): void
    {
        foreach (['qrcode-generator', 'qr-frame-font', 'qr-renderer', 'qr-drawing', 'qr-download'] as $file) {
            $this->assertFileEquals(
                public_path("js/{$file}.js"),
                public_path("js/app/{$file}.js"),
                "public/js/app/{$file}.js is stale. Run php artisan filament:assets, then restore public/css/filament and public/js/filament.",
            );
        }
    }
}
