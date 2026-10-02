<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Models\User;
use App\Support\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The static generator, as the Steps editor, shared by the homepage and the
 * Landing Pages: the link field in the hero, the code drawn in the browser as
 * it is typed, a Look step and a Download step, and the same two register
 * links with the same refs as before.
 */
class StaticGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_still_renders_the_whole_generator(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="static-qr-form"', false)
            ->assertSee('id="static-url"', false)
            ->assertSee('id="static-result"', false)
            ->assertSee('id="static-offer"', false)
            ->assertSee('ref='.SignupSource::StaticInline->value, false)
            ->assertSee('ref='.SignupSource::StaticOffer->value, false);
    }

    public function test_the_link_field_sits_in_the_hero_above_the_editor(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(strpos($page, 'id="static-url"'), strpos($page, 'class="eq-hero"'));
        $this->assertLessThan(strpos($page, 'id="static-result"'), strpos($page, 'id="static-url"'));
    }

    public function test_the_editor_is_hidden_until_a_valid_link_is_typed(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<div id="static-result" class="eq-editor" hidden>', false)
            ->assertSee('Your QR code appears here as soon as you type a link.');
    }

    public function test_the_wizard_is_look_then_download_and_nothing_more(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-step="look"', false)
            ->assertSee('data-step="download"', false)
            ->assertDontSee('data-step="logo"', false);
    }

    public function test_the_look_step_draws_its_looks_from_the_renderer_with_rounded_as_the_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="static-looks"', false)
            ->assertSee('Object.keys(QrRenderer.LOOKS)', false)
            ->assertSee('QrRenderer.defaultDesign()', false)
            ->assertSee('aria-pressed', false);
    }

    public function test_the_download_step_offers_png_at_four_sizes_and_svg(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        foreach ([512, 1024, 2048, 4096] as $size) {
            $this->assertStringContainsString('<option value="'.$size.'"', $page);
        }

        $this->assertStringContainsString('<option value="1024" selected>', $page);
        $this->assertStringContainsString('id="static-download-png"', $page);
        $this->assertStringContainsString('id="static-download-svg"', $page);
    }

    public function test_copy_and_share_ship_hidden_and_are_revealed_only_where_the_browser_can_do_them(): void
    {
        $page = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*id="static-copy"[^>]*\shidden>/', $page);
        $this->assertMatchesRegularExpression('/<button[^>]*id="static-share"[^>]*\shidden>/', $page);
        $this->assertStringContainsString('copyButton.hidden = !canCopy', $page);
        $this->assertStringContainsString('shareButton.hidden = !canShareFiles()', $page);
        $this->assertStringContainsString('navigator.canShare', $page);
        $this->assertStringContainsString('typeof window.ClipboardItem', $page);
    }

    public function test_the_page_loads_the_one_renderer_and_the_link_rules_versioned(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(Asset::versioned('js/qrcode-generator.js'), false)
            ->assertSee(Asset::versioned('js/qr-renderer.js'), false)
            ->assertSee(Asset::versioned('js/qr-link.js'), false);
    }

    public function test_the_old_server_round_trip_is_gone(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('qr/instant', false)
            ->assertDontSee('id="static-generate"', false)
            ->assertDontSee('id="static-qr-img"', false);

        $this->postJson('/qr/instant', ['url' => 'https://example.com'])->assertNotFound();
        $this->assertFalse(Route::has('qr.instant'));
    }

    public function test_the_wizard_has_no_fixed_width_that_could_overflow_a_phone(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertMatchesRegularExpression('/\.eq-editor \{[^}]*width: 100%;[^}]*max-width: 440px;/s', $css);
        $this->assertMatchesRegularExpression('/\.eq-editor-link \{[^}]*width: 100%;[^}]*max-width: 480px;/s', $css);
        $this->assertMatchesRegularExpression('/\.eq-looks \{[^}]*grid-template-columns: repeat\(4, 1fr\);/s', $css);
    }

    public function test_the_steps_expose_which_one_is_current_to_assistive_technology(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-current="step"', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-label="Your QR code"', false);
    }

    public function test_the_homepage_still_carries_the_generator_script(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("getElementById('static-qr-form')", false);
    }

    public function test_the_dynamic_teaser_stays_on_the_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Dynamic QR')
            ->assertSee('Log in to generate a dynamic QR');
    }

    public function test_the_homepage_identifies_itself_to_the_generator(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-page="home"', false);
    }

    public function test_the_page_defaults_to_the_homepage(): void
    {
        $this->blade('<x-static-generator />')
            ->assertSee('data-page="home"', false);
    }

    public function test_the_page_it_is_embedded_on_is_threaded_through(): void
    {
        $this->blade('<x-static-generator page="static-vs-dynamic-qr-code" />')
            ->assertSee('data-page="static-vs-dynamic-qr-code"', false)
            ->assertSee('id="static-qr-form"', false)
            ->assertSee('id="static-result"', false);
    }

    public function test_the_dynamic_teaser_is_not_part_of_the_generator(): void
    {
        $this->blade('<x-static-generator />')
            ->assertDontSee('Log in to generate a dynamic QR');
    }

    public function test_a_signed_in_user_gets_the_dashboard_link_instead_of_the_register_links(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('id="static-result"', false)
            ->assertDontSee('ref='.SignupSource::StaticInline->value, false)
            ->assertDontSee('id="static-offer"', false);
    }
}
