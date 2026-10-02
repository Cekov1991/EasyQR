<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Where the editor's logo upload lands. What a saved Design references is
 * covered by QrDesignLogoTest.
 */
class QrCodeLogoFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_old_format_and_error_correction_fields_no_longer_exist(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateQrCode::class)
            ->assertFormFieldDoesNotExist('options.format')
            ->assertFormFieldDoesNotExist('options.errorCorrection');
    }

    /**
     * The upload disk must be the disk `Storage::get()` reads from. Both resolve
     * to the same value locally, so only an explicit assertion catches a
     * production divergence.
     */
    public function test_the_upload_goes_to_the_filesystem_default_not_the_filament_default(): void
    {
        Storage::fake('elsewhere');
        config(['filesystems.default' => 'elsewhere', 'filament.default_filesystem_disk' => 'public']);

        $user = User::factory()->create();
        $page = Livewire::actingAs($user)->test(CreateQrCode::class);
        $path = null;

        $page->set('designLogoUpload', UploadedFile::fake()->image('logo.png', 100, 100))
            ->call('storeDesignLogo')
            ->assertReturned(function (array $answer) use (&$path): bool {
                $path = $answer['path'] ?? null;

                return $path !== null;
            });

        Storage::disk('elsewhere')->assertExists($path);
        $this->assertStringStartsWith("qr-logos/{$user->id}/", $path);
        $this->assertSame([], Storage::disk('local')->allFiles('qr-logos'));
    }
}
