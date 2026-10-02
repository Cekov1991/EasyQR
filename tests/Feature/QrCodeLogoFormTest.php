<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Models\QrCode;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QrCodeLogoFormTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = 'https://easyqr.link/aB3xK9pQ';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * A pending upload carrying bytes the rendered code can be measured
     * against, rather than the noise `UploadedFile::fake()->image()` produces.
     */
    private function pendingUpload(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->redPng());
    }

    private function redPng(int $width = 200, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function createThroughTheForm(User $user, array $options): QrCode
    {
        Livewire::actingAs($user)
            ->test(CreateQrCode::class)
            ->fillForm([
                'name' => 'Logo code',
                'type' => 'static',
                'qr_content_type' => 'website',
                'qr_content_data' => ['url' => self::CONTENT],
                'options' => $options,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return $user->qrCodes()->sole();
    }

    /**
     * @param  class-string  $page
     */
    private function assertLogoFieldIsConfigured(string $page, string $panel): void
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));

        Livewire::actingAs(User::factory()->create())
            ->test($page)
            ->assertFormFieldExists('options.logo_path', function (FileUpload $field): bool {
                $this->assertSame(config('filesystems.default'), $field->getDiskName());
                $this->assertSame('qr-logos', $field->getDirectory());
                $this->assertFalse($field->isMultiple(), 'A multiple upload would not dehydrate to a single path.');
                $this->assertSame(2048, $field->getMaxSize());

                $accepted = $field->getAcceptedFileTypes();
                $this->assertNotContains('image/svg+xml', $accepted);
                $this->assertContains('image/png', $accepted);

                return true;
            });
    }

    public function test_the_logo_field_is_configured_on_the_admin_create_form(): void
    {
        $this->assertLogoFieldIsConfigured(CreateQrCode::class, 'admin');
    }

    /**
     * The upload disk must be pinned to the disk `Storage::get()` reads from.
     * Both resolve to the same value locally, so only an explicit assertion
     * catches a production divergence.
     */
    public function test_the_upload_disk_follows_the_filesystem_default_not_the_filament_default(): void
    {
        config([
            'filesystems.disks.elsewhere' => config('filesystems.disks.local'),
            'filesystems.default' => 'elsewhere',
            'filament.default_filesystem_disk' => 'public',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(CreateQrCode::class)
            ->assertFormFieldExists('options.logo_path', function (FileUpload $field): bool {
                $this->assertSame('elsewhere', $field->getDiskName());

                return true;
            });
    }

    public function test_the_old_format_and_error_correction_fields_no_longer_exist(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateQrCode::class)
            ->assertFormFieldDoesNotExist('options.format')
            ->assertFormFieldDoesNotExist('options.errorCorrection');
    }

    public function test_creating_with_a_logo_persists_the_path_and_writes_no_image(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, [
            'logo_path' => $this->pendingUpload(),
        ]);

        $this->assertNotEmpty($qrCode->options['logo_path']);
        $this->assertStringStartsWith('qr-logos/', $qrCode->options['logo_path']);
        Storage::assertExists($qrCode->options['logo_path']);

        $this->assertNull($qrCode->qr_code_image);
        $this->assertSame([$qrCode->options['logo_path']], Storage::allFiles());
    }

    public function test_a_code_created_without_a_logo_has_no_logo_path(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, []);

        $this->assertTrue(blank($qrCode->options['logo_path'] ?? null));
        $this->assertFalse(QrCode::hasLogo($qrCode->options));
    }
}
