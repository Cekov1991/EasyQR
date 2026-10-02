<?php

namespace Tests\Feature;

use App\Enums\TrackedEvent;
use App\Models\SiteEvent;
use App\Models\User;
use App\Services\AgentaOS\AgentaOsClient;
use App\Services\AgentaOS\AgentaOsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The server-side half of the funnel.
 *
 * These four moments already existed in the code and were simply never written
 * down, which is why questions as basic as "how many people who generate a free
 * code go on to open a checkout" had no answer.
 */
class EventRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function assertRecordedOnce(TrackedEvent $event): void
    {
        $this->assertSame(
            1,
            SiteEvent::query()->named($event)->count(),
            "Expected exactly one {$event->value} row.",
        );
    }

    public function test_drawing_a_static_code_is_counted_when_the_page_reports_it(): void
    {
        $this->postJson(route('events.log'), ['event' => TrackedEvent::StaticQrGenerated->value])
            ->assertNoContent();

        $this->assertRecordedOnce(TrackedEvent::StaticQrGenerated);
    }

    public function test_loading_the_homepage_counts_nothing(): void
    {
        $this->get('/')->assertOk();

        $this->assertSame(0, SiteEvent::query()->count());
    }

    public function test_opening_a_checkout_is_counted(): void
    {
        config(['services.agentaos.payment_links.yearly' => 'link_123']);

        $this->mock(AgentaOsClient::class)
            ->shouldReceive('createCheckout')
            ->once()
            ->andReturn([
                'session_id' => 'sess_123',
                'checkoutUrl' => 'https://checkout.example/sess_123',
                'currency' => 'USD',
            ]);

        $this->actingAs(User::factory()->create())
            ->post(route('billing.subscribe'), ['plan' => 'yearly'])
            ->assertRedirect('https://checkout.example/sess_123');

        $this->assertRecordedOnce(TrackedEvent::CheckoutStarted);
    }

    /**
     * A checkout that never opened is not a checkout started. Counting it would
     * make an AgentaOS outage look like buyer abandonment.
     */
    public function test_a_checkout_that_could_not_be_opened_is_not_counted(): void
    {
        config(['services.agentaos.payment_links.yearly' => 'link_123']);

        $this->mock(AgentaOsClient::class)
            ->shouldReceive('createCheckout')
            ->once()
            ->andThrow(new AgentaOsException('Upstream is down'));

        $this->actingAs(User::factory()->create())
            ->post(route('billing.subscribe'), ['plan' => 'yearly']);

        $this->assertSame(0, SiteEvent::query()->named(TrackedEvent::CheckoutStarted)->count());
    }

    public function test_an_unconfigured_payment_link_is_not_counted_as_a_checkout(): void
    {
        config(['services.agentaos.payment_links.yearly' => null]);

        $this->actingAs(User::factory()->create())
            ->post(route('billing.subscribe'), ['plan' => 'yearly']);

        $this->assertSame(0, SiteEvent::query()->named(TrackedEvent::CheckoutStarted)->count());
    }

    public function test_returning_through_the_success_url_is_counted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.success'))
            ->assertRedirect();

        $this->assertRecordedOnce(TrackedEvent::CheckoutCompleted);
    }

    public function test_returning_through_the_cancel_url_is_counted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.cancel'))
            ->assertRedirect();

        $this->assertRecordedOnce(TrackedEvent::CheckoutAbandoned);
    }

    /**
     * The enum is the vocabulary, so a case must be storable and readable back
     * as itself — the cast is what lets the report command group by a case
     * rather than by a magic string.
     */
    public function test_a_recorded_event_reads_back_as_its_enum_case(): void
    {
        TrackedEvent::OfferShown->record(variant: 'offer', context: ['surface' => 'homepage']);

        $stored = SiteEvent::query()->sole();

        $this->assertSame(TrackedEvent::OfferShown, $stored->name);
        $this->assertSame('offer', $stored->variant);
        $this->assertSame(['surface' => 'homepage'], $stored->context);
        $this->assertNotNull($stored->occurred_at);
    }

    /**
     * A new case is untrusted until it is deliberately named client-loggable, so
     * that adding an event cannot accidentally open a public write path to it.
     */
    public function test_the_server_recorded_events_are_not_client_loggable(): void
    {
        foreach ([
            TrackedEvent::CheckoutStarted,
            TrackedEvent::CheckoutCompleted,
            TrackedEvent::CheckoutAbandoned,
        ] as $event) {
            $this->assertFalse(
                $event->isClientLoggable(),
                "{$event->value} is recorded server-side and must not be writable by a browser.",
            );
        }
    }
}
