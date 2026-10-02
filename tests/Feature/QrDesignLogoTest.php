<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use App\Models\QrCode;
use App\Models\User;
use App\Support\QrDesignOptions;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owners put a logo on a saved code (ADR-0005): the editor uploads it to the
 * bucket, the Design references it, and replaced or removed files are deleted.
 */
class QrDesignLogoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.com/menu';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function logoSettings(string $path, array $changes = []): array
    {
        return ['path' => $path, 'shape' => 'rounded', 'size' => 20, 'padding' => 2, 'backing' => true, 'clearSpace' => true, ...$changes];
    }

    /**
     * @param  array<string, mixed>  $logo
     * @return array<string, mixed>
     */
    private function designWithLogo(array $logo): array
    {
        return [...QrDesignOptions::defaultDesign(), 'logo' => $logo];
    }

    /**
     * Uploads through the editor's page the way the browser does and returns what
     * the page answers: the stored path, or the reason it refused.
     *
     * @return array{path?: string, error?: string}
     */
    private function upload(Testable $page, UploadedFile $file): array
    {
        $answer = [];

        $page->set('designLogoUpload', $file)
            ->call('storeDesignLogo')
            ->assertReturned(function (array $returned) use (&$answer): bool {
                $answer = $returned;

                return true;
            });

        return $answer;
    }

    private function createPage(User $user): Testable
    {
        return Livewire::actingAs($user)->test(CreateQrCode::class);
    }

    private function editPage(QrCode $code): Testable
    {
        return Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()]);
    }

    private function savedCodeWithLogo(User $user): QrCode
    {
        $page = $this->createPage($user);
        $path = $this->upload($page, UploadedFile::fake()->image('first.png', 300, 300))['path'];

        $page->fillForm([
            'name' => 'Menu',
            'type' => 'static',
            'qr_content_type' => 'website',
            'qr_content_data' => ['url' => self::URL],
            'options' => ['design' => $this->designWithLogo($this->logoSettings($path))],
        ])->call('create')->assertHasNoFormErrors();

        return $user->qrCodes()->sole();
    }

    public function test_an_owner_uploads_a_logo_and_the_saved_design_references_it(): void
    {
        $user = User::factory()->create();
        $page = $this->createPage($user);
        $path = $this->upload($page, UploadedFile::fake()->image('logo.png', 300, 300))['path'];

        Storage::assertExists($path);

        $design = $this->designWithLogo($this->logoSettings($path, ['shape' => 'circle', 'size' => 18, 'padding' => 1.5]));

        $page->fillForm([
            'name' => 'Menu',
            'type' => 'static',
            'qr_content_type' => 'website',
            'qr_content_data' => ['url' => self::URL],
            'options' => ['design' => $design],
        ])->call('create')->assertHasNoFormErrors();

        $stored = $user->qrCodes()->sole();

        $this->assertSame($design, $stored->options['design']);
        $this->assertNull($stored->qr_code_image);
        $this->assertSame([$path], Storage::allFiles());
    }

    public function test_an_uploaded_logo_is_squared_to_at_most_512_pixels(): void
    {
        $page = $this->createPage(User::factory()->create());

        foreach ([[2000, 400], [100, 600], [40, 20]] as [$width, $height]) {
            $path = $this->upload($page, UploadedFile::fake()->image('logo.png', $width, $height))['path'];
            [$storedWidth, $storedHeight] = getimagesizefromstring(Storage::get($path));

            $this->assertSame($storedWidth, $storedHeight, "{$width}x{$height} was not squared.");
            $this->assertLessThanOrEqual(512, $storedWidth);
        }
    }

    public function test_a_small_logo_is_not_scaled_up(): void
    {
        $page = $this->createPage(User::factory()->create());
        $path = $this->upload($page, UploadedFile::fake()->image('logo.png', 40, 20))['path'];

        $this->assertSame(40, getimagesizefromstring(Storage::get($path))[0]);
    }

    public function test_every_accepted_picture_type_can_be_uploaded(): void
    {
        $page = $this->createPage(User::factory()->create());

        foreach (['logo.png', 'logo.jpg', 'logo.webp'] as $name) {
            $answer = $this->upload($page, UploadedFile::fake()->image($name, 200, 200));

            $this->assertArrayHasKey('path', $answer, $name);
        }
    }

    public function test_something_that_is_not_a_picture_is_refused_and_stores_nothing(): void
    {
        $page = $this->createPage(User::factory()->create());

        foreach ([
            UploadedFile::fake()->createWithContent('logo.png', 'not a picture at all'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ] as $file) {
            $answer = $this->upload($page, $file);

            $this->assertArrayNotHasKey('path', $answer);
            $this->assertNotEmpty($answer['error']);
        }

        $this->assertSame([], Storage::allFiles());
    }

    public function test_a_picture_over_five_megabytes_is_refused(): void
    {
        $page = $this->createPage(User::factory()->create());

        $answer = $this->upload($page, UploadedFile::fake()->image('big.png', 200, 200)->size(5121));

        $this->assertArrayNotHasKey('path', $answer);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_asking_to_store_without_an_upload_answers_with_a_reason(): void
    {
        $this->createPage(User::factory()->create())
            ->call('storeDesignLogo')
            ->assertReturned(fn (array $answer): bool => isset($answer['error']) && ! isset($answer['path']));
    }

    public function test_the_logo_goes_to_the_default_disk_with_nothing_but_the_storage_api(): void
    {
        $root = storage_path('framework/testing/disks/bare-key');

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
        config(['filesystems.disks.bare' => ['driver' => 'bare-key', 'root' => $root], 'filesystems.default' => 'bare']);

        $user = User::factory()->create();
        $code = $this->savedCodeWithLogo($user);

        Storage::disk('bare')->assertExists($code->options['design']['logo']['path']);
        Storage::disk('local')->assertMissing($code->options['design']['logo']['path']);

        Storage::disk('bare')->deleteDirectory('qr-logos');
    }

    public function test_replacing_a_logo_deletes_the_old_file_once_the_code_is_saved(): void
    {
        $code = $this->savedCodeWithLogo(User::factory()->create());
        $old = $code->options['design']['logo']['path'];

        $page = $this->editPage($code);
        $new = $this->upload($page, UploadedFile::fake()->image('second.png', 300, 300))['path'];

        $this->assertNotSame($old, $new);
        Storage::assertExists($old);

        $page->fillForm(['options' => ['design' => $this->designWithLogo($this->logoSettings($new))]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::assertMissing($old);
        Storage::assertExists($new);
        $this->assertSame($new, $code->refresh()->options['design']['logo']['path']);
    }

    public function test_removing_a_logo_deletes_its_file_once_the_code_is_saved(): void
    {
        $code = $this->savedCodeWithLogo(User::factory()->create());
        $old = $code->options['design']['logo']['path'];

        $this->editPage($code)
            ->fillForm(['options' => ['design' => QrDesignOptions::defaultDesign()]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::assertMissing($old);
        $this->assertNull($code->refresh()->options['design']['logo']);
    }

    public function test_changing_only_the_logos_settings_keeps_its_file(): void
    {
        $code = $this->savedCodeWithLogo(User::factory()->create());
        $path = $code->options['design']['logo']['path'];

        $this->editPage($code)
            ->fillForm(['options' => ['design' => $this->designWithLogo($this->logoSettings($path, ['shape' => 'circle', 'size' => 15]))]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::assertExists($path);
        $this->assertSame('circle', $code->refresh()->options['design']['logo']['shape']);
    }

    public function test_an_uploaded_but_unsaved_replacement_leaves_the_saved_logo_alone(): void
    {
        $code = $this->savedCodeWithLogo(User::factory()->create());
        $saved = $code->options['design']['logo']['path'];

        $this->upload($this->editPage($code), UploadedFile::fake()->image('second.png', 300, 300));

        Storage::assertExists($saved);
        $this->assertSame($saved, $code->refresh()->options['design']['logo']['path']);
    }

    public function test_a_logo_belonging_to_another_owner_cannot_be_put_on_a_code(): void
    {
        $other = $this->savedCodeWithLogo(User::factory()->create());
        $theirs = $other->options['design']['logo']['path'];
        $me = User::factory()->create();

        $this->createPage($me)->fillForm([
            'name' => 'Mine',
            'type' => 'static',
            'qr_content_type' => 'website',
            'qr_content_data' => ['url' => self::URL],
            'options' => ['design' => $this->designWithLogo($this->logoSettings($theirs))],
        ])->call('create')->assertHasFormErrors(['options.design']);

        $this->assertSame(0, $me->qrCodes()->count());
    }

    public function test_a_code_keeps_a_logo_it_already_has_wherever_it_is_stored(): void
    {
        Storage::put('qr-logos/older-upload.png', 'png-bytes');
        $code = QrCode::factory()->create([
            'options' => ['design' => $this->designWithLogo($this->logoSettings('qr-logos/older-upload.png'))],
        ]);

        $this->editPage($code)
            ->fillForm(['options' => ['design' => $this->designWithLogo($this->logoSettings('qr-logos/older-upload.png', ['size' => 15]))]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::assertExists('qr-logos/older-upload.png');
        $this->assertSame(15, $code->refresh()->options['design']['logo']['size']);
    }

    public function test_a_path_that_is_not_an_uploaded_logo_is_refused(): void
    {
        Storage::put('secrets/passwords.txt', 'hunter2');
        $me = User::factory()->create();

        foreach (['secrets/passwords.txt', "qr-logos/{$me->id}/../../secrets/passwords.txt", '../.env', 'qr-logos/'.$me->id.'/'] as $path) {
            $this->createPage($me)->fillForm([
                'name' => 'Mine',
                'type' => 'static',
                'qr_content_type' => 'website',
                'qr_content_data' => ['url' => self::URL],
                'options' => ['design' => $this->designWithLogo($this->logoSettings($path))],
            ])->call('create')->assertHasFormErrors(['options.design']);
        }

        $this->assertSame(0, $me->qrCodes()->count());
        Storage::assertExists('secrets/passwords.txt');
    }

    public function test_the_controls_can_reach_every_padding_in_half_steps(): void
    {
        $user = User::factory()->create();
        $path = $this->upload($this->createPage($user), UploadedFile::fake()->image('logo.png', 300, 300))['path'];

        foreach ([0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5] as $padding) {
            $size = min(25, 25 - 2 * $padding);
            $size = max(10, floor($size));

            $this->createPage($user)->fillForm([
                'name' => "Padding {$padding}",
                'type' => 'static',
                'qr_content_type' => 'website',
                'qr_content_data' => ['url' => self::URL],
                'options' => ['design' => $this->designWithLogo($this->logoSettings($path, ['size' => $size, 'padding' => $padding]))],
            ])->call('create')->assertHasNoFormErrors();
        }

        $this->assertSame(11, $user->qrCodes()->count());
    }

    public function test_the_old_centre_logo_upload_is_gone_from_the_form(): void
    {
        $this->createPage(User::factory()->create())->assertFormFieldDoesNotExist('options.logo_path');
    }
}
