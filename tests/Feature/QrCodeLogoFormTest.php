<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Filament\Resources\QrCodeResource\Pages\ViewQrCode;
use App\Models\QrCode;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

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

    private function countsRedPixels(string $png): int
    {
        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image, 'The render is not a decodable image.');

        $count = 0;

        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $colour = imagecolorat($image, $x, $y);

                if ((($colour >> 16) & 0xFF) >= 200 && (($colour >> 8) & 0xFF) <= 60 && ($colour & 0xFF) <= 60) {
                    $count++;
                }
            }
        }

        imagedestroy($image);

        return $count;
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

    /**
     * The renderer forces PNG and high error correction regardless, so the
     * lock exists to stop the form promising something it will not honour.
     */
    public function test_the_format_and_error_correction_fields_lock_once_a_logo_is_uploaded(): void
    {
        $page = Livewire::actingAs(User::factory()->create())
            ->test(CreateQrCode::class)
            ->assertFormFieldIsEnabled('options.format')
            ->assertFormFieldIsEnabled('options.errorCorrection');

        $page->fillForm(['options' => ['logo_path' => $this->pendingUpload()]])
            ->assertFormFieldIsDisabled('options.format')
            ->assertFormFieldIsDisabled('options.errorCorrection');
    }

    public function test_creating_with_a_logo_persists_the_path_and_merges_the_image(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, [
            'style' => 'round',
            'format' => 'png',
            'size' => 300,
            'errorCorrection' => 'M',
            'logo_path' => $this->pendingUpload(),
        ]);

        $this->assertNotEmpty($qrCode->options['logo_path']);
        $this->assertStringStartsWith('qr-logos/', $qrCode->options['logo_path']);
        Storage::assertExists($qrCode->options['logo_path']);

        $this->assertGreaterThan(0, $this->countsRedPixels(Storage::get($qrCode->qr_code_image)));
    }

    public function test_a_submitted_svg_format_still_yields_a_png_when_a_logo_is_set(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, [
            'style' => 'round',
            'format' => 'svg',
            'size' => 300,
            'errorCorrection' => 'M',
            'logo_path' => $this->pendingUpload(),
        ]);

        $this->assertSame('png', $qrCode->options['format']);
        $this->assertStringEndsWith('.png', $qrCode->qr_code_image);
        $this->assertStringStartsWith("\x89PNG", Storage::get($qrCode->qr_code_image));
    }

    public function test_a_submitted_medium_error_correction_still_yields_high_when_a_logo_is_set(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, [
            'style' => 'round',
            'format' => 'png',
            'size' => 300,
            'errorCorrection' => 'M',
            'logo_path' => $this->pendingUpload(),
        ]);

        $this->assertSame('H', $qrCode->options['errorCorrection']);
    }

    public function test_a_code_created_without_a_logo_keeps_its_chosen_format_and_error_correction(): void
    {
        $user = User::factory()->create();

        $qrCode = $this->createThroughTheForm($user, [
            'style' => 'round',
            'format' => 'svg',
            'size' => 300,
            'errorCorrection' => 'L',
        ]);

        $this->assertSame('svg', $qrCode->options['format']);
        $this->assertSame('L', $qrCode->options['errorCorrection']);
        $this->assertStringEndsWith('.svg', $qrCode->qr_code_image);
        $this->assertTrue(blank($qrCode->options['logo_path'] ?? null));
        $this->assertFalse(QrCode::hasLogo($qrCode->options));
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(QrCode $record): array
    {
        $component = Livewire::actingAs($record->user)
            ->test(ViewQrCode::class, ['record' => $record->getKey()])
            ->callAction('download_all_formats');

        $component->assertFileDownloaded();

        $path = tempnam(sys_get_temp_dir(), 'qr-zip');
        file_put_contents($path, base64_decode((string) data_get($component->effects, 'download.content')));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[(string) $zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($path);

        return $entries;
    }

    private function recordWithLogo(): QrCode
    {
        Storage::put('qr-logos/square.png', $this->redPng());

        return QrCode::factory()
            ->for(User::factory()->create(['email_verified_at' => now()]))
            ->create([
                'name' => 'Team Poster',
                'qr_content_data' => ['url' => self::CONTENT],
                'options' => ['size' => 300, 'logo_path' => 'qr-logos/square.png'],
            ]);
    }

    /**
     * SVG and EPS cannot carry the overlay, so bundling them would hand back a
     * file that silently disagrees with the PNG beside it.
     */
    public function test_the_zip_holds_only_the_png_when_a_logo_is_set(): void
    {
        $entries = $this->zipEntries($this->recordWithLogo());

        $this->assertSame(['qr-Team Poster.png'], array_keys($entries));
        $this->assertGreaterThan(0, $this->countsRedPixels($entries['qr-Team Poster.png']));
    }

    public function test_the_zip_still_holds_every_format_without_a_logo(): void
    {
        $record = QrCode::factory()
            ->for(User::factory()->create(['email_verified_at' => now()]))
            ->create(['name' => 'Team Poster', 'qr_content_data' => ['url' => self::CONTENT]]);

        $entries = array_keys($this->zipEntries($record));
        sort($entries);

        $this->assertSame([
            'qr-Team Poster.eps',
            'qr-Team Poster.png',
            'qr-Team Poster.svg',
        ], $entries);
    }

    public function test_download_original_serves_the_merged_png(): void
    {
        $record = $this->recordWithLogo();

        $component = Livewire::actingAs($record->user)
            ->test(ViewQrCode::class, ['record' => $record->getKey()])
            ->callAction('download_original');

        $component->assertFileDownloaded();

        $this->assertSame('qr-Team Poster.png', data_get($component->effects, 'download.name'));
        $this->assertGreaterThan(
            0,
            $this->countsRedPixels(base64_decode((string) data_get($component->effects, 'download.content'))),
        );
    }
}
