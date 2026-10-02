<?php

namespace Tests\Feature;

use App\Models\QrCode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use Tests\TestCase;

class QrCodeStyleTest extends TestCase
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
     * @param  array<string, mixed>  $overrides
     */
    private function render(array $overrides = []): string
    {
        return (string) QrCode::buildGenerator(array_merge([
            'format' => 'png',
            'size' => 300,
            'errorCorrection' => 'M',
        ], $overrides))->generate(self::CONTENT);
    }

    public function test_the_classic_square_style_matches_an_unstyled_render(): void
    {
        $unstyled = (string) QrCodeGenerator::format('png')
            ->size(300)
            ->errorCorrection('M')
            ->color(0, 0, 0)
            ->generate(self::CONTENT);

        $this->assertSame($unstyled, $this->render(['style' => 'square']));
    }

    public function test_each_style_produces_a_distinct_image(): void
    {
        $renders = [];

        foreach (array_keys(QrCode::QR_STYLES) as $style) {
            $renders[$style] = md5($this->render(['style' => $style]));
        }

        $this->assertCount(count(QrCode::QR_STYLES), array_unique($renders));
    }

    public function test_an_unknown_or_missing_style_falls_back_to_the_default(): void
    {
        $default = $this->render(['style' => QrCode::DEFAULT_STYLE]);

        $this->assertSame($default, $this->render());
        $this->assertSame($default, $this->render(['style' => 'not-a-style']));
    }

    public function test_the_rounded_style_leaves_the_eye_shape_inherited(): void
    {
        $inheritedEyes = (string) QrCodeGenerator::format('png')
            ->size(300)
            ->errorCorrection('M')
            ->color(0, 0, 0)
            ->style('round', 0.5)
            ->generate(self::CONTENT);

        $this->assertSame($inheritedEyes, $this->render(['style' => 'round']));
    }

    /**
     * Dotted modules dissolve the finder patterns unless an eye style is set
     * explicitly, which stops scanners locating the symbol at all.
     */
    public function test_the_dot_style_sets_an_explicit_eye_shape(): void
    {
        $withExplicitEye = (string) QrCodeGenerator::format('png')
            ->size(300)
            ->errorCorrection('M')
            ->color(0, 0, 0)
            ->style('dot', 0.85)
            ->eye('circle')
            ->generate(self::CONTENT);

        $inheritedEyes = (string) QrCodeGenerator::format('png')
            ->size(300)
            ->errorCorrection('M')
            ->color(0, 0, 0)
            ->style('dot', 0.85)
            ->generate(self::CONTENT);

        $this->assertSame($withExplicitEye, $this->render(['style' => 'dot']));
        $this->assertNotSame($inheritedEyes, $this->render(['style' => 'dot']));
    }

    public function test_every_style_renders_in_every_supported_format(): void
    {
        foreach (array_keys(QrCode::QR_STYLES) as $style) {
            foreach (['png', 'svg', 'eps'] as $format) {
                $render = $this->render(['style' => $style, 'format' => $format]);

                $this->assertNotEmpty($render, "{$style} produced no output as {$format}");
            }
        }
    }

    public function test_a_new_code_is_stored_in_the_rounded_style_by_default(): void
    {
        $qrCode = QrCode::factory()->create([
            'qr_content_data' => ['url' => self::CONTENT],
        ]);

        $stored = Storage::get($qrCode->qr_code_image);

        $this->assertSame($this->render(['style' => 'round']), $stored);
        $this->assertNotSame($this->render(['style' => 'square']), $stored);
    }

    /**
     * The zip of alternate formats used to drop the record's colour, so the
     * PNG inside it did not match its SVG and EPS siblings.
     */
    public function test_alternate_format_renders_keep_the_chosen_colour(): void
    {
        $options = ['format' => 'png', 'size' => 300, 'errorCorrection' => 'M', 'color' => '#ff0000'];

        $red = (string) QrCode::buildGenerator(array_merge($options, ['format' => 'svg']))->generate(self::CONTENT);
        $black = $this->render(['format' => 'svg']);

        $this->assertNotSame($black, $red);
        $this->assertStringContainsStringIgnoringCase('ff0000', $red);
    }

    public function test_each_style_sample_is_a_distinct_inline_svg(): void
    {
        $samples = [];

        foreach (array_keys(QrCode::QR_STYLES) as $style) {
            $sample = QrCode::styleSample($style);

            $this->assertStringStartsWith('data:image/svg+xml;base64,', $sample);

            $samples[$style] = $sample;
        }

        $this->assertCount(count(QrCode::QR_STYLES), array_unique($samples));
    }

    /**
     * The homepage no longer asks the server for a code, so there is no server
     * default to hold it to: the editor opens on the Rounded Look, which the
     * renderer's tests (tests/js) pin as the default Design.
     */
    public function test_the_server_no_longer_makes_a_code_for_the_homepage(): void
    {
        $this->postJson('/qr/instant', ['url' => self::CONTENT])->assertNotFound();

        $this->get('/')->assertOk();
    }
}
