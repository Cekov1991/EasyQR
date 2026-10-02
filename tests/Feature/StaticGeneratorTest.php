<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The static generator, lifted out of the homepage so a Landing Page can embed
 * the same thing. The homepage must not notice the move: same form, same
 * result panel, same two register links with the same refs.
 */
class StaticGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_still_renders_the_whole_generator(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="static-qr-form"', false)
            ->assertSee('id="static-result"', false)
            ->assertSee('id="static-offer"', false)
            ->assertSee('ref='.SignupSource::StaticInline->value, false)
            ->assertSee('ref='.SignupSource::StaticOffer->value, false);
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
