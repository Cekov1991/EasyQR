<?php

namespace Tests\Feature;

use App\Support\Asset;
use App\Support\QrDesignOptions;
use Tests\TestCase;

/**
 * The Blade controls are rendered from the Design option data, the same file the
 * renderer and the browser controls read, so these tests walk the data rather
 * than a list of their own.
 */
class QrDesignControlsTest extends TestCase
{
    protected function tearDown(): void
    {
        QrDesignOptions::use(null);

        parent::tearDown();
    }

    public function test_every_choice_in_the_data_is_a_button_with_its_label(): void
    {
        $view = $this->blade('<x-qr-design-controls.shapes /><x-qr-design-controls.frame /><x-qr-design-controls.logo />');

        foreach (['dot', 'corner', 'eye', 'frame', 'logoShape'] as $setting) {
            $set = QrDesignOptions::optionSet($setting);

            $view->assertSee($set['label']);

            foreach ($set['values'] as $value => $label) {
                $view->assertSee('data-setting="'.$setting.'" data-value="'.$value.'"', false);
                $view->assertSeeInOrder(['data-setting="'.$setting.'" data-value="'.$value.'"', $label], false);
            }
        }
    }

    public function test_a_value_added_to_the_data_appears_in_the_controls_without_touching_blade(): void
    {
        $data = QrDesignOptions::all();
        $data['options']['dot']['values']['hexagon'] = 'Hexagon';
        $data['colours']['codeColor']['swatches'][] = '#abcdef';
        QrDesignOptions::use($data);

        $this->blade('<x-qr-design-controls.shapes /><x-qr-design-controls.colours />')
            ->assertSee('data-setting="dot" data-value="hexagon"', false)
            ->assertSee('Hexagon')
            ->assertSee('data-colour-swatch="codeColor" data-value="#abcdef"', false);
    }

    public function test_every_colour_has_its_swatches_picker_and_hex_field(): void
    {
        $view = $this->blade('<x-qr-design-controls.colours />');

        foreach (QrDesignOptions::colours() as $setting => $colour) {
            $view->assertSee($colour['label']);
            $view->assertSee('data-colour-picker="'.$setting.'"', false);
            $view->assertSee('data-colour-hex="'.$setting.'"', false);

            foreach ($colour['swatches'] as $swatch) {
                $view->assertSee('data-colour-swatch="'.$setting.'" data-value="'.$swatch.'"', false);
            }
        }
    }

    public function test_the_frame_text_field_takes_the_length_the_data_sets(): void
    {
        $data = QrDesignOptions::all();
        $data['frameText']['max'] = 12;
        QrDesignOptions::use($data);

        $this->blade('<x-qr-design-controls.frame />')->assertSee('maxlength="12"', false);
    }

    public function test_the_logo_ranges_and_accepted_files_come_from_the_data(): void
    {
        $logo = QrDesignOptions::logo();

        $this->blade('<x-qr-design-controls.logo />')
            ->assertSee('min="'.$logo['size']['min'].'" max="'.$logo['size']['max'].'"', false)
            ->assertSee('min="'.$logo['padding']['min'].'" max="'.$logo['padding']['max'].'"', false)
            ->assertSee('accept="'.implode(',', $logo['fileTypes']).'"', false);
    }

    public function test_svg_logos_are_not_an_accepted_type(): void
    {
        $this->assertNotContains('image/svg+xml', QrDesignOptions::logo()['fileTypes']);
        $this->assertEqualsCanonicalizing(['image/png', 'image/jpeg', 'image/webp'], QrDesignOptions::logo()['fileTypes']);

        $this->blade('<x-qr-design-controls.logo />')->assertDontSee('svg', false);
    }

    public function test_the_browser_gets_exactly_the_data_the_controls_are_rendered_from(): void
    {
        $html = (string) $this->blade('<x-qr-design-controls.data />');

        preg_match('/globalThis\.QrDesignOptions = (.*);<\/script>/s', $html, $match);

        $this->assertSame(QrDesignOptions::all(), json_decode($match[1] ?? 'null', true));
    }

    public function test_the_homepage_serves_the_editor_with_its_scripts_versioned(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(Asset::versioned('js/qrcode-generator.js'), false)
            ->assertSee(Asset::versioned('js/qr-frame-font.js'), false)
            ->assertSee(Asset::versioned('js/qr-renderer.js'), false)
            ->assertSee(Asset::versioned('js/qr-design-controls.js'), false)
            ->assertSee(Asset::versioned('js/qr-link.js'), false)
            ->assertSee(Asset::versioned('css/qr-design-controls.css'), false);
    }
}
