<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Enums\SignupSource;
use App\Enums\TrackedEvent;
use App\Filament\Pages\Auth\Register;
use App\Models\SiteEvent;
use App\Models\User;
use App\Support\SubscriptionPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The upgrade offer under a freshly downloaded static code, and the one piece of
 * per-person attribution in the whole funnel.
 *
 * The homepage had no test of its own before the event work, so some of what
 * follows is simply its first coverage. The rest guards two things that are easy
 * to break silently: a price that misdescribes what the customer is charged, and
 * a `?ref=` parameter turning into a column anyone can write anything into.
 */
class StaticOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_homepage_renders(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * The code is drawn and downloaded in the browser now, so there is no
     * server endpoint that makes one: the link goes nowhere.
     */
    public function test_there_is_no_server_endpoint_that_makes_a_code(): void
    {
        $this->postJson('/qr/instant', ['url' => 'https://example.com'])->assertNotFound();
    }

    /**
     * A download is a click in the browser, and the offer follows it. Both
     * formats start the same hand-off.
     */
    public function test_both_downloads_report_themselves_and_then_open_the_offer(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("handleDownload('png')", $content);
        $this->assertStringContainsString("handleDownload('svg')", $content);
        $this->assertMatchesRegularExpression(
            '/function handleDownload\(format\) \{\s*logEvent\(\'qr_downloaded\', \{ format: format \}\);\s*window\.setTimeout\(revealOffer, REVEAL_DELAY_MS\);/',
            $content,
        );
    }

    public function test_the_offer_is_in_the_markup_for_a_visitor(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="static-offer"', false)
            ->assertSee('That code can never be changed');
    }

    /**
     * Opened a beat after the download rather than in the same tick. A modal
     * thrown up instantly lands on top of the browser's own download UI, which
     * reads as having interrupted the thing they came for.
     */
    public function test_the_offer_waits_for_the_download_to_start(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('REVEAL_DELAY_MS', false);
    }

    /**
     * The dialog ships closed. A <dialog> without an `open` attribute is hidden
     * by the user agent, and it is opened by script only once a download has
     * started — shipping it open would put the offer in front of the download
     * button, which is the opposite of the intent.
     */
    public function test_the_offer_ships_closed(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('<dialog id="static-offer" class="eq-offer"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<dialog[^>]*id="static-offer"[^>]*\sopen/',
            $response->getContent(),
        );
    }

    /**
     * A real <dialog> rather than a styled div, because showModal() is what
     * brings the focus trap, Escape-to-close, inerting of the page behind it and
     * restoration of focus on close. It was an inline panel first and was
     * effectively invisible: tall result panel above it on a phone, quiet card
     * below the fold on a desktop.
     */
    public function test_the_offer_is_a_modal_dialog_opened_by_script(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('<dialog', false);
        $response->assertSee('showModal', false);
    }

    /**
     * Labelled by its own heading, so a screen reader announces what the dialog
     * is for rather than just that one opened.
     */
    public function test_the_dialog_is_labelled_by_its_heading(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-labelledby="static-offer-title"', false)
            ->assertSee('id="static-offer-title"', false);
    }

    /**
     * Every way out of a modal must be counted the same, and there is exactly one
     * place doing the counting — the dialog's own close event, which fires for
     * the two buttons, Escape and a backdrop click alike.
     */
    public function test_dismissal_is_counted_once_for_every_route_out(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($content, TrackedEvent::OfferDismissed->value),
            'offer_dismissed should be logged from a single place: the close event.',
        );
    }

    /**
     * The refusal is remembered before it is reported, and the order is the
     * bug this pins. It was the other way round: any throw out of the reporting
     * call skipped the line that persisted the dismissal, so the modal came back
     * on the next download and no dismissal was ever counted. `fetch` is
     * routinely replaced by extensions and injected dev tooling, which is
     * exactly where such a throw comes from.
     *
     * Not being asked again is a promise to the person who said no. The count is
     * only for us, so it must come second.
     */
    public function test_the_refusal_is_remembered_before_it_is_reported(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $closeHandler = $this->closeHandlerSource($content);

        $persistedAt = strpos($closeHandler, 'offerDismissedThisView = true');
        $reportedAt = strpos($closeHandler, TrackedEvent::OfferDismissed->value);

        $this->assertNotFalse($persistedAt, 'The dismissal is not persisted at all.');
        $this->assertNotFalse($reportedAt, 'The dismissal is not reported at all.');
        $this->assertLessThan(
            $reportedAt,
            $persistedAt,
            'The dismissal must be remembered before it is reported, so a failure to '
            .'report cannot cost the person their refusal.',
        );
    }

    /**
     * A storage write that throws must not cost the refusal either, so the
     * in-memory flag is checked first and set unconditionally.
     */
    public function test_a_refusal_survives_storage_being_unavailable(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('offerDismissedThisView', $content);
        $this->assertMatchesRegularExpression(
            '/if \(offerDismissedThisView\)\s*\{\s*return true;/',
            $content,
            'The in-memory refusal must short-circuit before localStorage is consulted.',
        );
    }

    /**
     * Reporting an event must never throw into its caller. The try has to wrap
     * the fetch call itself, not only the promise it returns.
     */
    public function test_reporting_an_event_cannot_throw_into_its_caller(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/try \{\s*fetch\(/',
            $content,
            'fetch() must be inside a try, not merely have .catch() attached: a patched '
            .'fetch can throw synchronously.',
        );
    }

    /**
     * Pulls the dialog close handler out of the page so the assertions above are
     * about that block rather than about the order of unrelated occurrences
     * elsewhere in the script.
     */
    private function closeHandlerSource(string $content): string
    {
        $start = strpos($content, "offer.addEventListener('close'");
        $this->assertNotFalse($start, 'The dialog close handler is missing.');

        return substr($content, $start, 1400);
    }

    /**
     * Taking the offer must not also count as refusing it. The dialog closes on
     * the way to the register page, and that close is not a dismissal.
     */
    public function test_accepting_the_offer_is_not_also_counted_as_dismissing_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('offerAccepted', false);
    }

    /**
     * Someone signed in already has an account; the pitch would be for something
     * they have. It is removed from the markup rather than hidden, so it cannot
     * be revealed by script that does not know better.
     */
    public function test_the_offer_is_absent_for_a_signed_in_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('id="static-offer"', false);
    }

    /**
     * Every figure comes from config, so a price change cannot ship a homepage
     * that still quotes the old one.
     */
    public function test_the_offer_quotes_the_prices_from_config(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(SubscriptionPrice::formatted(Plan::Monthly))
            ->assertSee(SubscriptionPrice::formatted(Plan::Yearly))
            ->assertSee(config('subscription.trial_days').'-day free trial');
    }

    /**
     * The load-bearing pricing test, and the reason it changed. The offer used
     * to quote the yearly price divided by twelve, a figure nobody is charged
     * and that no month could be cancelled after, so it was only safe beside
     * the real annual charge. It now quotes the monthly plan's own price, which
     * is a real charge, and the yearly sits beside it as the cheaper option.
     * The derived figure must not reappear on this page.
     */
    public function test_the_offer_quotes_a_real_charge_with_the_yearly_alternative_beside_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(SubscriptionPrice::formatted(Plan::Monthly))
            ->assertSee('/month', false)
            ->assertSee('or '.SubscriptionPrice::formatted(Plan::Yearly).' a year')
            ->assertSee('— save '.SubscriptionPrice::saving(Plan::Yearly))
            ->assertDontSee(SubscriptionPrice::monthlyEquivalent(Plan::Yearly));
    }

    public function test_the_offer_links_to_registration_carrying_its_ref(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('ref='.SignupSource::StaticOffer->value, false);
    }

    /**
     * The quiet inline link stays where it is, and carries its own ref. The pair
     * is the experiment: both are shown to everyone, so comparing their
     * registration rates asks whether the loud offer beats an unobtrusive line of
     * text without suppressing either for anybody.
     */
    public function test_the_quiet_inline_link_still_exists_and_is_tagged(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Create a dynamic QR')
            ->assertSee('ref='.SignupSource::StaticInline->value, false);
    }

    /**
     * Both arms are reachable on the same page for the same visitor. If one ever
     * stops rendering, the comparison silently becomes a measurement of nothing.
     */
    public function test_both_arms_are_offered_to_the_same_visitor(): void
    {
        $response = $this->get('/')->assertOk();

        foreach ([SignupSource::StaticOffer, SignupSource::StaticInline] as $source) {
            $response->assertSee('ref='.$source->value, false);
        }
    }

    public function test_a_registration_from_the_quiet_link_records_its_own_source(): void
    {
        Livewire::withQueryParams(['ref' => SignupSource::StaticInline->value])
            ->test(Register::class)
            ->fillForm([
                'name' => 'Edsger',
                'email' => 'edsger@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register');

        $this->assertSame(
            SignupSource::StaticInline,
            User::query()->where('email', 'edsger@example.com')->sole()->signup_source,
        );
    }

    /**
     * No holdout was built, so no event may carry an arm. A `variant` reaching
     * the table would mean a suppression mechanism had been added without the
     * endpoint's allowlist being added with it.
     */
    public function test_no_event_carries_an_experiment_arm(): void
    {
        $this->postJson(route('events.log'), [
            'event' => TrackedEvent::OfferShown->value,
            'variant' => 'holdout',
        ])->assertStatus(422);

        $this->postJson(route('events.log'), ['event' => TrackedEvent::OfferShown->value])
            ->assertNoContent();

        $this->assertNull(SiteEvent::query()->sole()->variant);
    }

    public function test_the_offer_reports_its_events_to_the_endpoint(): void
    {
        $response = $this->get('/')->assertOk();

        foreach ([TrackedEvent::OfferShown, TrackedEvent::OfferDismissed, TrackedEvent::OfferClicked] as $event) {
            $response->assertSee($event->value);
        }
    }

    /**
     * Dismissal must not be a cookie. A marketing cookie contradicts section 2c
     * of the Privacy Policy and would drag the whole site behind a real consent
     * gate for the sake of one hidden panel.
     */
    public function test_dismissal_is_remembered_in_local_storage_not_a_cookie(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('localStorage', false);
        $this->assertStringNotContainsString(
            'document.cookie',
            $response->getContent(),
            'Remembering the dismissal in a cookie would make section 2c of the Privacy Policy false.',
        );
    }

    public function test_a_registration_from_the_offer_records_its_source(): void
    {
        $this->withoutMiddleware()
            ->get(route('filament.admin.auth.register', ['ref' => SignupSource::StaticOffer->value]));

        Livewire::withQueryParams(['ref' => SignupSource::StaticOffer->value])
            ->test(Register::class)
            ->fillForm([
                'name' => 'Ada',
                'email' => 'ada@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register');

        $this->assertSame(
            SignupSource::StaticOffer,
            User::query()->where('email', 'ada@example.com')->sole()->signup_source,
        );
    }

    /**
     * The reason this is an enum and not a string column. `?ref=` comes off a URL
     * a stranger can put anything into, and an unvalidated column would become a
     * free-text sink filled by whoever felt like filling it.
     */
    public function test_an_invented_ref_is_discarded_rather_than_stored(): void
    {
        Livewire::withQueryParams(['ref' => 'not-a-source-we-published'])
            ->test(Register::class)
            ->fillForm([
                'name' => 'Grace',
                'email' => 'grace@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register');

        $this->assertNull(User::query()->where('email', 'grace@example.com')->sole()->signup_source);
    }

    public function test_a_registration_with_no_ref_records_no_source(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Alan',
                'email' => 'alan@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register');

        $this->assertNull(User::query()->where('email', 'alan@example.com')->sole()->signup_source);
    }

    /**
     * The column is set from an allowlisted query parameter, never from posted
     * form input. It is not in $fillable, but note that that is not what stops
     * this: AppServiceProvider calls Model::unguard(), so mass-assignment
     * guarding is off application-wide and fill() would happily write it. What
     * stops it is that `signup_source` is not in the form schema, so
     * $this->form->getState() discards it before registration ever sees it.
     */
    public function test_a_source_posted_through_the_form_is_discarded(): void
    {
        Livewire::test(Register::class)
            ->set('data.name', 'Eve')
            ->set('data.email', 'eve@example.com')
            ->set('data.password', 'password-that-is-long')
            ->set('data.passwordConfirmation', 'password-that-is-long')
            ->set('data.signup_source', SignupSource::StaticOffer->value)
            ->call('register');

        $this->assertNull(User::query()->where('email', 'eve@example.com')->sole()->signup_source);
    }

    /**
     * The component property is public, so Livewire lets the browser assign it
     * directly — bypassing the check done at mount. It is therefore resolved
     * through the enum again at the write site. Without that, the allowlist would
     * only ever have applied to the honest path.
     */
    public function test_a_tampered_component_property_is_discarded(): void
    {
        Livewire::test(Register::class)
            ->set('signupSource', 'whatever-i-typed')
            ->fillForm([
                'name' => 'Mallory',
                'email' => 'mallory@example.com',
                'password' => 'password-that-is-long',
                'passwordConfirmation' => 'password-that-is-long',
            ])
            ->call('register');

        $this->assertNull(User::query()->where('email', 'mallory@example.com')->sole()->signup_source);
    }

    /**
     * The documented gap, asserted so it is a known state rather than a surprise.
     * If someone removes the unguard() call, this test fails and the User model's
     * $fillable list starts meaning what it says.
     */
    public function test_mass_assignment_guarding_is_currently_disabled_application_wide(): void
    {
        $user = new User;
        $user->fill(['signup_source' => SignupSource::StaticOffer->value]);

        $this->assertSame(
            SignupSource::StaticOffer,
            $user->signup_source,
            'Model::unguard() in AppServiceProvider appears to have been removed. That is an '
            .'improvement — update this test and the User model docblock to match.',
        );
    }

    public function test_the_privacy_policy_discloses_the_source_column(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Which part of our site sent you to the registration form')
            ->assertSee(SignupSource::StaticOffer->value);
    }
}
