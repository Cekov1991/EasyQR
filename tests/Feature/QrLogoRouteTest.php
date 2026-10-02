<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportDisablingBackButtonCache\SupportDisablingBackButtonCache;
use Tests\TestCase;

/**
 * The logo route (ADR-0005): every page draws a saved logo from here so the
 * canvas that makes a PNG is never tainted by a cross-origin image, and only
 * the code's Owner can fetch it.
 */
class QrLogoRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        // A Livewire component booted by an earlier test leaves this switched on for the
        // whole process, and it would stamp no-store on a response it has nothing to do with.
        SupportDisablingBackButtonCache::$disableBackButtonCache = false;
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function codeWithLogo(?User $user = null): QrCode
    {
        $user ??= User::factory()->create();
        $path = "qr-logos/{$user->id}/logo.png";

        Storage::put($path, $this->pngBytes());

        return QrCode::factory()->for($user)->create([
            'options' => ['design' => [
                'version' => 1,
                'logo' => ['path' => $path, 'shape' => 'rounded', 'size' => 20, 'padding' => 2, 'backing' => true, 'clearSpace' => true],
            ]],
        ]);
    }

    public function test_the_owner_gets_the_logo_bytes(): void
    {
        $code = $this->codeWithLogo();

        $response = $this->actingAs($code->user)->get(route('qr.logo', $code));

        $response->assertOk();
        $this->assertSame($this->pngBytes(), $response->streamedContent());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_the_logo_is_cached_for_a_long_time_but_only_by_the_owners_browser(): void
    {
        $code = $this->codeWithLogo();

        $cache = $this->actingAs($code->user)->get(route('qr.logo', $code))->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cache);
        $this->assertStringNotContainsString('public', $cache);
        $this->assertMatchesRegularExpression('/max-age=(\d+)/', $cache);
        preg_match('/max-age=(\d+)/', $cache, $age);
        $this->assertGreaterThanOrEqual(86400 * 30, (int) $age[1]);
    }

    public function test_another_user_is_refused(): void
    {
        $code = $this->codeWithLogo();

        $this->actingAs(User::factory()->create())->get(route('qr.logo', $code))->assertForbidden();
    }

    public function test_a_guest_is_refused(): void
    {
        $code = $this->codeWithLogo();

        $response = $this->get(route('qr.logo', $code));

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertStringNotContainsString($this->pngBytes(), (string) $response->getContent());
    }

    public function test_a_code_without_a_logo_has_nothing_to_serve(): void
    {
        $code = QrCode::factory()->create();

        $this->actingAs($code->user)->get(route('qr.logo', $code))->assertNotFound();
    }

    public function test_a_logo_file_that_has_gone_has_nothing_to_serve(): void
    {
        $code = $this->codeWithLogo();
        Storage::delete($code->options['design']['logo']['path']);

        $this->actingAs($code->user)->get(route('qr.logo', $code))->assertNotFound();
    }

    public function test_it_works_on_a_disk_whose_path_is_only_a_bare_key(): void
    {
        Storage::extend('bare-key', function ($app, array $config) {
            $adapter = new LocalFilesystemAdapter($config['root']);

            return new class(new Filesystem($adapter, $config), $adapter, $config) extends FilesystemAdapter
            {
                public function path($path): string
                {
                    return $path;
                }
            };
        });
        config(['filesystems.disks.bare' => ['driver' => 'bare-key', 'root' => storage_path('framework/testing/disks/bare-route')], 'filesystems.default' => 'bare']);

        $code = $this->codeWithLogo();

        $response = $this->actingAs($code->user)->get(route('qr.logo', $code));

        $response->assertOk();
        $this->assertSame($this->pngBytes(), $response->streamedContent());

        Storage::disk('bare')->deleteDirectory('qr-logos');
    }

    public function test_the_url_a_page_draws_from_changes_when_the_logo_does(): void
    {
        $code = $this->codeWithLogo();
        $before = $code->logoUrl();

        $options = $code->options;
        $options['design']['logo']['path'] = "qr-logos/{$code->user_id}/other.png";
        $code->update(['options' => $options]);

        $this->assertNotSame($before, $code->logoUrl());
        $this->assertNull(QrCode::factory()->create()->logoUrl());
    }
}
