<?php

namespace Tests\Feature;

use App\Enums\TrackedEvent;
use App\Http\Requests\LogSiteEventRequest;
use App\Models\SiteEvent;
use App\Support\LandingPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public endpoint through which a browser reports what only it can see.
 *
 * Four of the eight counted events leave no trace on the server, so the page has
 * to tell us about them — which makes this the one path where a stranger can
 * cause a row to be written. Most of what follows is therefore about what the
 * endpoint refuses, not what it accepts. The refusals are the feature.
 */
class ClientEventLoggingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function report(array $payload): TestResponse
    {
        return $this->postJson(route('events.log'), $payload);
    }

    /**
     * Every event a browser is allowed to raise, straight from the enum, so a
     * newly client-loggable case is covered the moment it is added.
     *
     * @return array<string, array{0: TrackedEvent}>
     */
    public static function clientLoggableEventProvider(): array
    {
        $cases = array_filter(
            TrackedEvent::cases(),
            fn (TrackedEvent $event): bool => $event->isClientLoggable(),
        );

        return collect($cases)
            ->mapWithKeys(fn (TrackedEvent $event): array => [$event->value => [$event]])
            ->all();
    }

    /**
     * The other half of the same list. These are the dangerous ones.
     *
     * @return array<string, array{0: TrackedEvent}>
     */
    public static function serverOnlyEventProvider(): array
    {
        $cases = array_filter(
            TrackedEvent::cases(),
            fn (TrackedEvent $event): bool => ! $event->isClientLoggable(),
        );

        return collect($cases)
            ->mapWithKeys(fn (TrackedEvent $event): array => [$event->value => [$event]])
            ->all();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function formatProvider(): array
    {
        return collect(LogSiteEventRequest::FORMATS)
            ->mapWithKeys(fn (string $format): array => [$format => [$format]])
            ->all();
    }

    #[DataProvider('formatProvider')]
    public function test_a_download_reported_by_the_browser_is_counted(string $format): void
    {
        $this->report(['event' => TrackedEvent::QrDownloaded->value, 'format' => $format])
            ->assertNoContent();

        $event = SiteEvent::query()->sole();

        $this->assertSame(TrackedEvent::QrDownloaded, $event->name);
        $this->assertSame(['page' => LandingPages::HOME, 'format' => $format], $event->context);
    }

    #[DataProvider('clientLoggableEventProvider')]
    public function test_every_client_loggable_event_is_accepted(TrackedEvent $event): void
    {
        $payload = ['event' => $event->value];

        if ($event === TrackedEvent::QrDownloaded) {
            $payload['format'] = 'png';
        }

        $this->report($payload)->assertNoContent();

        $this->assertSame(1, SiteEvent::query()->named($event)->count());
    }

    /**
     * The reason the endpoint exists in this shape rather than as a generic
     * writer. `checkout_completed` is the only record we have of a sale; if a
     * browser could assert it, the number would be worth nothing, and nothing on
     * these rows would let us tell a real one from ten thousand forged ones.
     */
    #[DataProvider('serverOnlyEventProvider')]
    public function test_a_server_recorded_event_cannot_be_forged_through_the_endpoint(TrackedEvent $event): void
    {
        $this->report(['event' => $event->value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    public function test_an_unknown_event_name_is_rejected(): void
    {
        $this->report(['event' => 'something_we_never_defined'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    public function test_an_event_is_required(): void
    {
        $this->report([])
            ->assertStatus(422)
            ->assertJsonValidationErrors('event');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * A download row with no format cannot answer the one question it exists to
     * answer, so it is refused rather than stored half-useful.
     */
    public function test_a_download_must_say_which_format(): void
    {
        $this->report(['event' => TrackedEvent::QrDownloaded->value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    public function test_an_unrecognised_format_is_rejected(): void
    {
        $this->report(['event' => TrackedEvent::QrDownloaded->value, 'format' => 'pdf'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * Refused rather than dropped, so that an `offer_shown` row cannot quietly
     * grow a context key that means nothing on it.
     */
    public function test_a_format_is_refused_on_an_event_that_has_none(): void
    {
        $this->report(['event' => TrackedEvent::OfferShown->value, 'format' => 'png'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * The experiment arm is the server's to assign. Until the holdout mechanism
     * exists there are no arms at all, and an accepted-but-unvalidated variant
     * would be a public free-text column — exactly the unbounded cardinality
     * TrackedEvent's second rule forbids.
     */
    public function test_the_caller_cannot_choose_a_variant(): void
    {
        $this->report(['event' => TrackedEvent::OfferShown->value, 'variant' => 'whatever-i-like'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * `context` is not a field on this request, so there is no path for one to
     * arrive from outside. A caller that sends anything else — a URL, a label, a
     * whole object — has it discarded before the row is built.
     */
    public function test_nothing_the_caller_invents_reaches_the_stored_row(): void
    {
        $this->report([
            'event' => TrackedEvent::QrDownloaded->value,
            'format' => 'png',
            'context' => ['url' => 'https://example.com/a-private-menu'],
            'user_id' => 1,
            'label' => 'free text',
        ])->assertNoContent();

        $stored = json_encode(SiteEvent::query()->sole()->getAttributes());

        $this->assertStringNotContainsString('a-private-menu', (string) $stored);
        $this->assertStringNotContainsString('free text', (string) $stored);
        $this->assertSame(['page' => LandingPages::HOME, 'format' => 'png'], SiteEvent::query()->sole()->context);
    }

    /**
     * The endpoint answers with nothing at all. The caller is script that ignores
     * the result, and a body would only invite something to start relying on one.
     */
    public function test_the_endpoint_returns_no_body(): void
    {
        $response = $this->report(['event' => TrackedEvent::OfferDismissed->value]);

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
    }

    /**
     * The ceiling is what keeps a forgeable endpoint from being a free write
     * loop. It sits in middleware, ahead of validation, so rejected payloads are
     * counted too — otherwise the cheapest way to flood it would be to send
     * garbage.
     */
    public function test_the_endpoint_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->report(['event' => 'not-a-real-event'])->assertStatus(422);
        }

        $this->report(['event' => TrackedEvent::OfferShown->value])->assertStatus(429);

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * Behavioural CSRF cannot be exercised here — Laravel skips the check while
     * running tests — so this asserts the wiring instead: the route is in the web
     * group, which is what applies the token check, and it carries its own
     * ceiling. Both are easy to lose in a refactor and neither would fail loudly.
     */
    public function test_the_route_keeps_its_session_and_rate_protections(): void
    {
        $middleware = Route::getRoutes()->getByName('events.log')->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('throttle:30,1', $middleware);
    }

    /**
     * The page must post the name the endpoint accepts. They are separated by a
     * Blade render and an HTTP hop, so nothing else would catch a drift between
     * them until downloads had silently stopped being counted.
     */
    public function test_the_homepage_reports_downloads_to_the_endpoint(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('events.log'))
            ->assertSee(TrackedEvent::QrDownloaded->value);
    }
}
