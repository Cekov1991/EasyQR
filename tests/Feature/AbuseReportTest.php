<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\User;
use App\Notifications\AbuseReported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The public channel for reporting a QR code that leads somewhere harmful.
 *
 * A dynamic QR code service is a link shortener with a printed front end, which
 * makes it a phishing vector. Terms section 4 has always prohibited that, but a
 * prohibition with no reporting channel is one we cannot enforce — and that is how
 * a merchant-of-record reviewer will read it.
 */
class AbuseReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        config(['site.support_email' => 'support@example.test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validReport(array $overrides = []): array
    {
        return array_merge([
            'code_url' => 'https://easy-qr-code.com/q/abc123',
            'reason' => 'phishing',
            'details' => 'It opened a fake bank login page asking for my card details.',
            'reporter_email' => 'worried@example.test',
        ], $overrides);
    }

    public function test_the_report_form_is_publicly_reachable(): void
    {
        $this->get('/report')
            ->assertOk()
            ->assertSee('Report a QR code');
    }

    public function test_a_report_notifies_the_support_address(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport())
            ->assertRedirect(route('report.create'))
            ->assertSessionHas('status', 'report-received');

        Notification::assertSentOnDemand(
            AbuseReported::class,
            fn ($notification, $channels, $notifiable) => $channels === ['mail']
                && $notifiable->routes['mail'] === 'support@example.test',
        );
    }

    /**
     * A report we have to look up by hand is a report that waits. The mail must
     * arrive with the owner and the current destination already resolved.
     */
    public function test_a_report_resolves_the_code_owner_and_destination(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['email' => 'seller@example.test']);
        $qrCode = QrCode::factory()->for($owner)->dynamic()->create([
            'short_url' => 'abc123',
            'qr_content_data' => ['url' => 'https://phishing.example/login'],
        ]);

        $this->post('/report', $this->validReport(['code_url' => 'https://easy-qr-code.com/q/abc123']));

        Notification::assertSentOnDemand(AbuseReported::class, function ($notification) use ($qrCode) {
            $mail = $notification->toMail((object) [])->render();

            return str_contains($mail, 'seller@example.test')
                && str_contains($mail, $qrCode->short_url);
        });
    }

    /**
     * Reporters paste whole links, type fragments off a poster, or copy the code
     * out of a scanner app. A bare short code has to resolve too.
     */
    public function test_a_bare_short_code_still_resolves_to_the_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create(['email' => 'seller@example.test']);
        QrCode::factory()->for($owner)->dynamic()->create(['short_url' => 'xyz789']);

        $this->post('/report', $this->validReport(['code_url' => 'xyz789']));

        Notification::assertSentOnDemand(
            AbuseReported::class,
            fn ($notification) => str_contains($notification->toMail((object) [])->render(), 'seller@example.test'),
        );
    }

    /**
     * A mistyped reference, a static code that never touched our servers, or a
     * code since deleted. Still worth knowing about, so it must not fail.
     */
    public function test_a_report_for_an_unknown_code_is_still_delivered(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport(['code_url' => 'https://easy-qr-code.com/q/nothere']))
            ->assertRedirect(route('report.create'));

        Notification::assertSentOnDemand(
            AbuseReported::class,
            fn ($notification) => str_contains(
                $notification->toMail((object) [])->render(),
                'could not match this to a code',
            ),
        );
    }

    public function test_a_report_requires_the_code_the_reason_and_the_details(): void
    {
        Notification::fake();

        $this->post('/report', [])
            ->assertSessionHasErrors(['code_url', 'reason', 'details']);

        Notification::assertNothingSent();
    }

    public function test_a_report_rejects_a_reason_outside_the_list(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport(['reason' => 'i_just_dont_like_it']))
            ->assertSessionHasErrors('reason');

        Notification::assertNothingSent();
    }

    public function test_a_report_rejects_details_too_short_to_investigate(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport(['details' => 'bad']))
            ->assertSessionHasErrors('details');

        Notification::assertNothingSent();
    }

    /**
     * Requiring contact details suppresses reports, and we do not need to reply in
     * order to act on one.
     */
    public function test_a_report_may_omit_the_reporter_email(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport(['reporter_email' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('report.create'));

        Notification::assertSentOnDemand(
            AbuseReported::class,
            fn ($notification) => str_contains(
                $notification->toMail((object) [])->render(),
                'did not leave an address',
            ),
        );
    }

    /**
     * Distinct from sending the field as null: `validated()` drops a key that was
     * never submitted, so the payload reaching the notification has no such index
     * at all. Our own form always posts the input, but nothing else has to.
     */
    public function test_a_report_that_omits_the_reporter_email_field_entirely_is_delivered(): void
    {
        Notification::fake();

        $report = $this->validReport();
        unset($report['reporter_email']);

        $this->post('/report', $report)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('report.create'));

        Notification::assertSentOnDemand(
            AbuseReported::class,
            fn ($notification) => str_contains(
                $notification->toMail((object) [])->render(),
                'did not leave an address',
            ),
        );
    }

    public function test_a_report_rejects_a_malformed_reporter_email(): void
    {
        Notification::fake();

        $this->post('/report', $this->validReport(['reporter_email' => 'not-an-address']))
            ->assertSessionHasErrors('reporter_email');

        Notification::assertNothingSent();
    }

    /**
     * The form sends mail to our own support address on an unauthenticated
     * request. Unthrottled it is an open relay into the inbox we rely on to act.
     */
    public function test_reports_are_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/report', $this->validReport())->assertRedirect();
        }

        $this->post('/report', $this->validReport())->assertStatus(429);
    }

    /**
     * A reporting channel nobody can find is not a reporting channel. This is also
     * what a reviewer checks: the prohibition in the Terms has to be actionable.
     */
    public function test_every_public_page_links_to_the_report_form(): void
    {
        foreach (['/', '/pricing', '/faq', '/terms-and-conditions', '/privacy-policy', '/refund-policy'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee(route('report.create'), false);
        }
    }

    public function test_the_terms_describe_what_happens_to_a_reported_code(): void
    {
        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('investigate every report')
            ->assertSee('disable it without notice')
            // The clause that reserves the right we actually need: a dynamic
            // destination can change after printing, so we judge a code by where
            // it points when we look at it.
            ->assertSee('pointed when it was created');
    }

    /**
     * The form is a utility, not a page we want ranking for our brand.
     */
    public function test_the_report_form_is_not_indexed(): void
    {
        $this->get('/report')
            ->assertOk()
            ->assertSee('noindex', false);
    }
}
