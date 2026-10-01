<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Filament\Pages\Auth\Register;
use App\Models\User;
use App\Support\LandingPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The second fact a registration records: which Landing Page the clicked link
 * sat on (ADR-0004).
 *
 * Like the Signup Source it rides on, it comes off a URL a stranger can type
 * anything into, so what is guarded here is mostly what does not get stored:
 * an invented slug, a page still in review, a page with no source beside it,
 * and a value swapped in the component between mount and submit.
 */
class SignupLandingPageTest extends TestCase
{
    use RefreshDatabase;

    private const HUB = LandingPages::HUB;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Registers through the panel with the given query parameters and returns
     * the account it made, asserting the registration itself went through.
     * Signs the new account out again, so a test can register twice.
     *
     * @param  array<string, string|array<int, string>>  $query
     */
    private function registerWith(array $query, string $email = 'ada@example.com'): User
    {
        Livewire::withQueryParams($query)
            ->test(Register::class)
            ->fillForm([
                'name' => 'Ada',
                'email' => $email,
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        auth()->logout();

        return User::query()->where('email', $email)->sole();
    }

    public function test_the_hub_cta_links_to_registration_with_its_source_and_page(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/'.self::HUB)
            ->assertOk()
            ->assertSee(Register::linkFrom(SignupSource::LandingCta, self::HUB));
    }

    /**
     * The generator keeps its own refs on a Landing Page, so the offer against
     * the inline link can still be compared there, and both carry the page.
     */
    public function test_the_generator_links_on_a_landing_page_carry_their_own_ref_and_the_page(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/'.self::HUB)
            ->assertOk()
            ->assertSee(Register::linkFrom(SignupSource::StaticOffer, self::HUB))
            ->assertSee(Register::linkFrom(SignupSource::StaticInline, self::HUB));
    }

    public function test_the_homepage_links_carry_no_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(Register::linkFrom(SignupSource::StaticOffer))
            ->assertSee(Register::linkFrom(SignupSource::StaticInline))
            ->assertDontSee('&amp;page=', false);
    }

    public function test_registering_via_the_hub_cta_credits_the_hub(): void
    {
        $this->publishLandingPage(self::HUB);

        $user = $this->registerWith(['ref' => SignupSource::LandingCta->value, 'page' => self::HUB]);

        $this->assertSame(SignupSource::LandingCta, $user->signup_source);
        $this->assertSame(self::HUB, $user->signup_landing_page);
    }

    public function test_registering_via_the_offer_on_a_landing_page_credits_the_page(): void
    {
        $this->publishLandingPage(self::HUB);

        $user = $this->registerWith(['ref' => SignupSource::StaticOffer->value, 'page' => self::HUB]);

        $this->assertSame(SignupSource::StaticOffer, $user->signup_source);
        $this->assertSame(self::HUB, $user->signup_landing_page);
    }

    public function test_registering_via_the_offer_on_the_homepage_credits_no_page(): void
    {
        $user = $this->registerWith(['ref' => SignupSource::StaticOffer->value]);

        $this->assertSame(SignupSource::StaticOffer, $user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    public function test_the_homepage_label_is_not_a_landing_page(): void
    {
        $user = $this->registerWith(['ref' => SignupSource::StaticOffer->value, 'page' => LandingPages::HOME]);

        $this->assertSame(SignupSource::StaticOffer, $user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    public function test_an_invented_page_is_discarded_and_the_registration_succeeds(): void
    {
        $this->publishLandingPage(self::HUB);

        $user = $this->registerWith(['ref' => SignupSource::LandingCta->value, 'page' => 'not-a-page-we-wrote']);

        $this->assertSame(SignupSource::LandingCta, $user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    /**
     * A page still in review cannot be reached, so a link claiming it was
     * clicked there is not one we published.
     */
    public function test_an_unpublished_page_is_discarded(): void
    {
        $user = $this->registerWith(['ref' => SignupSource::LandingCta->value, 'page' => self::HUB]);

        $this->assertSame(SignupSource::LandingCta, $user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    /**
     * A page names where a link sat, so without a link that we published
     * there is nothing for it to describe.
     */
    public function test_a_page_without_a_valid_ref_is_discarded(): void
    {
        $this->publishLandingPage(self::HUB);

        $invented = $this->registerWith(['ref' => 'not-a-source', 'page' => self::HUB], 'grace@example.com');
        $absent = $this->registerWith(['page' => self::HUB], 'alan@example.com');

        $this->assertNull($invented->signup_source);
        $this->assertNull($invented->signup_landing_page);
        $this->assertNull($absent->signup_source);
        $this->assertNull($absent->signup_landing_page);
    }

    /**
     * `?ref[]=` and `?page[]=` arrive as arrays. Attribution must never be the
     * reason a registration does not complete, so they read as unknown.
     */
    public function test_array_parameters_are_discarded_and_the_registration_succeeds(): void
    {
        $this->publishLandingPage(self::HUB);

        $arrayRef = $this->registerWith(['ref' => [SignupSource::LandingCta->value], 'page' => self::HUB], 'grace@example.com');
        $arrayPage = $this->registerWith(['ref' => SignupSource::LandingCta->value, 'page' => [self::HUB]], 'alan@example.com');

        $this->assertNull($arrayRef->signup_source);
        $this->assertNull($arrayRef->signup_landing_page);
        $this->assertSame(SignupSource::LandingCta, $arrayPage->signup_source);
        $this->assertNull($arrayPage->signup_landing_page);
    }

    /**
     * The property is public, so Livewire lets the browser set it. It is
     * resolved against the registry again at the write site.
     */
    public function test_a_tampered_page_property_is_discarded(): void
    {
        $this->publishLandingPage(self::HUB);

        Livewire::withQueryParams(['ref' => SignupSource::LandingCta->value, 'page' => self::HUB])
            ->test(Register::class)
            ->set('signupLandingPage', 'whatever-i-typed')
            ->fillForm([
                'name' => 'Mallory',
                'email' => 'mallory@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'mallory@example.com')->sole();

        $this->assertSame(SignupSource::LandingCta, $user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    /**
     * A tampered source takes the page down with it: a page is only ever
     * stored beside the source that was clicked on it.
     */
    public function test_a_tampered_source_property_takes_the_page_with_it(): void
    {
        $this->publishLandingPage(self::HUB);

        Livewire::withQueryParams(['ref' => SignupSource::LandingCta->value, 'page' => self::HUB])
            ->test(Register::class)
            ->set('signupSource', 'whatever-i-typed')
            ->fillForm([
                'name' => 'Mallory',
                'email' => 'mallory@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'mallory@example.com')->sole();

        $this->assertNull($user->signup_source);
        $this->assertNull($user->signup_landing_page);
    }

    public function test_a_page_posted_through_the_form_is_discarded(): void
    {
        $this->publishLandingPage(self::HUB);

        Livewire::test(Register::class)
            ->set('data.name', 'Eve')
            ->set('data.email', 'eve@example.com')
            ->set('data.password', 'password-that-is-long')
            ->set('data.passwordConfirmation', 'password-that-is-long')
            ->set('data.signup_landing_page', self::HUB)
            ->call('register');

        $this->assertNull(User::query()->where('email', 'eve@example.com')->sole()->signup_landing_page);
    }

    /**
     * Last click only: nothing is held for the visitor before they register.
     */
    public function test_nothing_is_put_in_the_session(): void
    {
        $this->publishLandingPage(self::HUB);

        $this->get('/'.self::HUB)->assertOk()->assertSessionMissing('signup_landing_page');

        $this->withoutMiddleware()
            ->get(Register::linkFrom(SignupSource::LandingCta, self::HUB))
            ->assertSessionMissing('signup_landing_page')
            ->assertSessionMissing('signup_source');
    }

    public function test_the_privacy_policy_discloses_the_page_column(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('we also note which guide');
    }
}
