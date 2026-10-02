<?php

namespace Tests\Feature;

use App\Enums\TrackedEvent;
use App\Models\SiteEvent;
use App\Support\LandingPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Which page a counted event happened on.
 *
 * The label is one of a closed set — `home`, or the slug of a published
 * Landing Page — so it stays a low-cardinality fact about our own pages and
 * never becomes a free-text column a stranger can write into.
 */
class SiteEventPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generate(array $payload = []): TestResponse
    {
        return $this->report(['event' => TrackedEvent::StaticQrGenerated->value] + $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function report(array $payload): TestResponse
    {
        return $this->postJson(route('events.log'), $payload);
    }

    /**
     * Every event a browser reports, with the extra fact it needs to be valid.
     *
     * @return array<string, array{0: array<string, string>}>
     */
    public static function clientEventProvider(): array
    {
        return collect(TrackedEvent::cases())
            ->filter(fn (TrackedEvent $event): bool => $event->isClientLoggable())
            ->mapWithKeys(fn (TrackedEvent $event): array => [$event->value => [
                $event === TrackedEvent::QrDownloaded
                    ? ['event' => $event->value, 'format' => 'png']
                    : ['event' => $event->value],
            ]])
            ->all();
    }

    public function test_a_generation_on_a_landing_page_carries_its_slug(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->generate(['page' => LandingPages::HUB])->assertNoContent();

        $this->assertSame(['page' => LandingPages::HUB], SiteEvent::query()->sole()->context);
    }

    public function test_a_generation_on_the_homepage_carries_home(): void
    {
        $this->generate(['page' => LandingPages::HOME])->assertNoContent();

        $this->assertSame(['page' => LandingPages::HOME], SiteEvent::query()->sole()->context);
    }

    /**
     * A homepage script cached from before the label existed sends none, and
     * the homepage is the only page that script could have come from.
     */
    public function test_a_generation_without_a_label_is_counted_as_home(): void
    {
        $this->generate()->assertNoContent();

        $this->assertSame(['page' => LandingPages::HOME], SiteEvent::query()->sole()->context);
    }

    /**
     * An empty label is no label: the request's empty-string conversion makes
     * it null, which means the homepage, rather than a refused request.
     */
    public function test_an_empty_label_is_counted_as_home(): void
    {
        $this->generate(['page' => ''])->assertNoContent();
        $this->report(['event' => TrackedEvent::OfferShown->value, 'page' => ''])->assertNoContent();

        $this->assertSame(2, SiteEvent::query()->onPage(LandingPages::HOME)->count());
    }

    #[DataProvider('clientEventProvider')]
    public function test_a_client_event_on_a_landing_page_carries_its_slug(array $payload): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->report($payload + ['page' => LandingPages::HUB])->assertNoContent();

        $this->assertSame(LandingPages::HUB, SiteEvent::query()->sole()->context['page']);
    }

    #[DataProvider('clientEventProvider')]
    public function test_a_client_event_without_a_label_is_counted_as_home(array $payload): void
    {
        $this->report($payload)->assertNoContent();

        $this->assertSame(LandingPages::HOME, SiteEvent::query()->sole()->context['page']);
    }

    /**
     * The download keeps its own rule: the format is still required and still
     * stored, next to the page rather than instead of it.
     */
    public function test_a_download_keeps_its_format_alongside_the_page(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->report(['event' => TrackedEvent::QrDownloaded->value, 'format' => 'svg', 'page' => LandingPages::HUB])
            ->assertNoContent();

        $this->assertEquals(
            ['page' => LandingPages::HUB, 'format' => 'svg'],
            SiteEvent::query()->sole()->context,
        );
    }

    public function test_a_download_from_a_landing_page_still_needs_a_format(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->report(['event' => TrackedEvent::QrDownloaded->value, 'page' => LandingPages::HUB])
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function refusedPageProvider(): array
    {
        return [
            'an invented slug' => ['not-a-page-of-ours'],
            'an unpublished landing page' => ['restaurant-menu-qr-code'],
            'a url' => ['https://example.com/a-private-menu'],
            'a different case' => ['HOME'],
            'an array' => [['home']],
        ];
    }

    #[DataProvider('refusedPageProvider')]
    public function test_the_generator_refuses_a_page_that_is_not_ours(mixed $page): void
    {
        $this->unpublishLandingPage('restaurant-menu-qr-code');

        $this->generate(['page' => $page])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page' => 'homepage or a published Landing Page']);

        $this->assertSame(0, SiteEvent::query()->count());
    }

    #[DataProvider('refusedPageProvider')]
    public function test_the_event_endpoint_refuses_a_page_that_is_not_ours(mixed $page): void
    {
        $this->unpublishLandingPage('restaurant-menu-qr-code');

        $this->report(['event' => TrackedEvent::OfferShown->value, 'page' => $page])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page' => 'homepage or a published Landing Page']);

        $this->assertSame(0, SiteEvent::query()->count());
    }

    /**
     * Being published is the only thing that makes a slug a valid label, so a
     * page that is withdrawn stops collecting counts once nobody can reach it.
     */
    public function test_a_page_is_a_valid_label_only_while_published(): void
    {
        $this->report(['event' => TrackedEvent::OfferShown->value, 'page' => 'qr-code-stopped-working'])
            ->assertNoContent();

        $this->unpublishLandingPage('qr-code-stopped-working');

        $this->report(['event' => TrackedEvent::OfferShown->value, 'page' => 'qr-code-stopped-working'])
            ->assertStatus(422);
    }

    /**
     * The script reads the label off the form and posts it with both
     * requests. They are separated by a render and an HTTP hop, so this holds
     * the field name in place.
     */
    public function test_the_generator_script_sends_its_page_with_every_request(): void
    {
        $this->publishLandingPage(LandingPages::HUB);

        $this->get(route(LandingPages::find(LandingPages::HUB)->routeName()))
            ->assertOk()
            ->assertSee('data-page="'.LandingPages::HUB.'"', false)
            ->assertSee('{ event: event, page: page }', false)
            ->assertSee('const page = form.dataset.page', false);
    }
}
