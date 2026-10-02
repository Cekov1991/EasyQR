<?php

namespace Tests\Feature;

use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a record owns on the bucket goes when the record goes. How a logo is
 * drawn is the renderer's business (tests/js); how it is uploaded and referenced
 * is covered by QrDesignLogoTest and QrLogoRouteTest.
 */
class QrCodeLogoTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = 'https://easyqr.link/aB3xK9pQ';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    private function storeLogo(string $path): string
    {
        Storage::put($path, 'png-bytes');

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function designWith(string $logo): array
    {
        return ['design' => [
            'version' => 1,
            'logo' => ['path' => $logo, 'shape' => 'rounded', 'size' => 20, 'padding' => 2, 'backing' => true, 'clearSpace' => true],
        ]];
    }

    public function test_deleting_a_record_removes_its_logo(): void
    {
        $logo = $this->storeLogo('qr-logos/square.png');

        $qrCode = QrCode::factory()->create([
            'qr_content_data' => ['url' => self::CONTENT],
            'options' => $this->designWith($logo),
        ]);

        Storage::assertExists($logo);

        $qrCode->delete();

        Storage::assertMissing($logo);
    }

    public function test_deleting_a_record_without_a_logo_leaves_other_logos_alone(): void
    {
        $other = $this->storeLogo('qr-logos/other.png');

        $qrCode = QrCode::factory()->create([
            'qr_content_data' => ['url' => self::CONTENT],
        ]);

        $qrCode->delete();

        Storage::assertExists($other);
        $this->assertDatabaseMissing('qr_codes', ['id' => $qrCode->id]);
    }

    public function test_deleting_a_record_whose_logo_has_already_gone_does_not_error(): void
    {
        $logo = $this->storeLogo('qr-logos/square.png');

        $qrCode = QrCode::factory()->create([
            'qr_content_data' => ['url' => self::CONTENT],
            'options' => $this->designWith($logo),
        ]);

        Storage::delete($logo);

        $qrCode->delete();

        $this->assertDatabaseMissing('qr_codes', ['id' => $qrCode->id]);
    }
}
