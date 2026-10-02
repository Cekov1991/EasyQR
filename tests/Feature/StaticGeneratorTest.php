<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The static generator, as the Steps editor shared by the homepage and the
 * Landing Pages. What the editor does in a browser (drawing, steps, downloads,
 * the scan check) is covered by tests/js/browser.test.mjs; what is checked here
 * is what the server promises: the page serves the generator, the promises it
 * makes in words, and the register links with the right refs.
 */
class StaticGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_serves_the_generator_with_both_register_links(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Your QR code appears here as soon as you type a link.')
            ->assertSee('ref='.SignupSource::StaticInline->value, false)
            ->assertSee('ref='.SignupSource::StaticOffer->value, false);
    }

    public function test_the_page_promises_only_what_is_true_of_a_code_drawn_in_the_browser(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Free, no account. We never store your code or its link.')
            ->assertSee('Your logo stays on your device. Nothing is uploaded.');
    }

    public function test_the_editor_is_headed_as_the_free_half_of_the_static_and_dynamic_pair(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Static QR', 'FREE', 'Dynamic QR', 'PAID']);
    }

    public function test_every_control_the_editor_needs_is_on_the_page(): void
    {
        $page = $this->get('/')->assertOk();

        foreach (['Modules', 'Corners', 'Eye', 'Frame', 'Logo shape', 'Code colour', 'Eye colour', 'Background colour', 'More shapes and colours'] as $heading) {
            $page->assertSee($heading);
        }
    }

    public function test_the_server_draws_no_code_for_the_page(): void
    {
        $this->postJson('/qr/instant', ['url' => 'https://example.com'])->assertNotFound();
        $this->assertFalse(Route::has('qr.instant'));
    }

    public function test_the_dynamic_teaser_stays_on_the_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Dynamic QR')
            ->assertSee('Log in to generate a dynamic QR');
    }

    public function test_the_dynamic_teaser_is_not_part_of_the_generator(): void
    {
        $this->blade('<x-static-generator />')
            ->assertDontSee('Log in to generate a dynamic QR');
    }

    public function test_the_page_it_is_embedded_on_travels_with_both_register_links(): void
    {
        $page = 'static-vs-dynamic-qr-code';

        $this->blade('<x-static-generator page="'.$page.'" />')
            ->assertSee('ref='.SignupSource::StaticOffer->value.'&amp;page='.$page, false)
            ->assertSee('ref='.SignupSource::StaticInline->value.'&amp;page='.$page, false);
    }

    public function test_a_signed_in_user_gets_the_dashboard_link_instead_of_the_register_links_and_the_offer(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Your QR code appears here as soon as you type a link.')
            ->assertSee(route('filament.admin.resources.qr-codes.create'), false)
            ->assertDontSee('ref='.SignupSource::StaticInline->value, false)
            ->assertDontSee('That code can never be changed');
    }
}
