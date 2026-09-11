<?php

namespace Tests\Feature\Subscription;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_refund_policy_is_reachable(): void
    {
        $this->get('/refund-policy')
            ->assertOk()
            ->assertSee('Refund Policy');
    }

    public function test_the_terms_describe_the_subscription_rather_than_the_retired_packages(): void
    {
        $response = $this->get('/terms-and-conditions');

        $response->assertOk();
        $response->assertSee('7-day');
        $response->assertSee('$49');
        $response->assertDontSee('[Currency]');
        $response->assertDontSee('Paid extensions');
    }

    /**
     * The parked marketing page advertised dynamic QR codes as free, claimed
     * they were "secure and encrypted", and carried a commented-out "12,000+
     * happy customers" badge — with zero paying customers. It was publicly
     * routable and indexable.
     *
     * AgentaOS asks us to attest that the site displays no false usage claims
     * and that pricing is clear. That page contradicted both, so it is gone
     * rather than parked. This is the guard against restoring the route.
     */
    public function test_the_parked_marketing_page_is_gone(): void
    {
        $this->get('/landing-page')->assertNotFound();
    }

    public function test_the_homepage_still_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_the_privacy_policy_is_reachable(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    /*
     * AgentaOS is merchant of record and reviews the site before approving live
     * payments. Their go-live form asks us to attest that pricing is "easily
     * accessible and clearly displayed to users before they purchase", and lists
     * "hidden pricing until checkout" as a failure. The price used to exist only
     * in clause 5 of the Terms and on the billing page behind the login, so a
     * reviewer could not learn it without registering.
     *
     * The tests below are that attestation. They must fail if the price stops
     * being reachable while logged out.
     */

    public function test_the_pricing_page_is_publicly_reachable(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Pricing');
    }

    public function test_the_pricing_page_states_the_price_period_and_tax_treatment(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('$49')
            ->assertSee('per year')
            ->assertSee('Tax is included')
            ->assertSee('merchant of record');
    }

    public function test_the_pricing_page_states_the_trial_needs_no_payment_details(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('7 days of full access', false)
            ->assertSee('no payment details to begin it');
    }

    public function test_the_pricing_page_explains_cancelling_and_the_refund_window(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('14 days')
            ->assertSee('until the end of the period you have')
            ->assertSee('refund-policy', false);
    }

    public function test_the_homepage_shows_the_dynamic_price_without_logging_in(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('$49')
            ->assertSee('per year');
    }

    public function test_the_pricing_page_follows_the_configured_price(): void
    {
        config(['subscription.plans.yearly.price' => 42]);

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('$42')
            ->assertDontSee('$49');
    }

    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_links_to_pricing(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee(url('/pricing'), false);
    }

    /**
     * The deleted marketing page sold the paid product as free: "Dynamic QR Code
     * Generator: Free & Powerful Solutions", "For Free", and "Free solutions for
     * individuals, SMBs, and enterprises". Dynamic codes cost money, and we have
     * no enterprise tier.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_does_not_advertise_dynamic_codes_as_free(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('For Free');
        $response->assertDontSee('Free &amp; Powerful', false);
        $response->assertDontSee('Free solutions');
    }

    /**
     * The Privacy Policy carried a whole section on Google Analytics, and the
     * cookie banner asked permission for it — but no gtag or GTM tag was ever
     * installed. A reviewer reading the policy and then the page source finds
     * consent being sought for tracking that does not exist, which is a false
     * statement about the site in the same category as a fake testimonial.
     *
     * If analytics are added later, this test should fail, and the fix is to
     * disclose them honestly rather than to delete the test.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_does_not_claim_analytics_we_do_not_run(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('Google Analytics');
        $response->assertDontSee('gtag', false);
        $response->assertDontSee('googletagmanager', false);
    }

    /**
     * Hotlinking fonts.googleapis.com sent every visitor's IP to Google from the
     * homepage, the pricing page and the privacy policy itself, with Google named
     * nowhere as a processor.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_loads_no_assets_from_google(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('googleapis.com', false);
        $response->assertDontSee('gstatic.com', false);
    }

    /**
     * Removing the false analytics claim must not leave the cookies we really do
     * set undisclosed. The notice and the policy have to describe them.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_notice_describes_only_essential_cookies(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('only essential cookies');
        $response->assertDontSee('Decline');
    }

    public function test_the_privacy_policy_still_discloses_the_cookies_we_do_set(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('session cookie')
            ->assertSee('cross-site request forgery')
            ->assertSee('no third-party tracking');
    }

    /**
     * You cannot decline a strictly necessary cookie and keep using the site, so
     * offering the button implied a choice that was never honoured — it set a
     * cookie and hid the banner.
     */
    public function test_there_is_no_cookie_decline_route(): void
    {
        $this->get('/cookies/decline')->assertNotFound();
    }

    /**
     * The deleted page claimed dynamic QR codes were "secure and encrypted" and
     * "use advanced encryption algorithms to protect your data". A QR code is an
     * encoding, not a cipher.
     *
     * Scoped to that claim rather than the word "encrypted", because the Privacy
     * Policy truthfully says passwords are encrypted and must keep saying so.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_makes_no_encryption_claim_about_qr_codes(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('secure and encrypted');
        $response->assertDontSee('advanced encryption');
        $response->assertDontSee('encryption algorithms');
    }

    /**
     * The go-live form's first attestation is that we display no usage claims or
     * testimonials. It was true only because a "12,000+ happy customers" badge
     * and a "15+ Years" experience badge were commented out on the parked page,
     * one uncomment away from making us liars. Both are deleted; this keeps them
     * from coming back.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_carries_no_social_proof_we_cannot_substantiate(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('happy customers');
        $response->assertDontSee('Of experience in business');
        $response->assertDontSee('testimonial');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPageProvider(): array
    {
        return [
            'homepage' => ['/'],
            'pricing' => ['/pricing'],
            'terms' => ['/terms-and-conditions'],
            'privacy' => ['/privacy-policy'],
            'refunds' => ['/refund-policy'],
            'report' => ['/report'],
        ];
    }

    /**
     * Bootstrap and the scraped template bundle are 11MB of vendor assets under
     * public/landing/. Nothing references them now that the parked marketing
     * layout is deleted, except the two icon files layouts.site still uses.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_loads_the_redesigned_stylesheet_and_nothing_else(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('css/site.css', false);
        $response->assertDontSee('landing/assets/vendor', false);
        $response->assertDontSee('landing/assets/css/main.css', false);
    }

    /**
     * The footer used to point at #about, #features and #faq, which existed on
     * the old marketing page. The homepage that replaced it has no such
     * sections, so every one of those links went nowhere.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_has_no_links_to_the_retired_marketing_anchors(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertDontSee('#about', false);
        $response->assertDontSee('#features', false);
        $response->assertDontSee('#faq', false);
    }

    /**
     * The contact address is cited in the Terms and the Privacy Policy as the
     * legal notice address, so it must come from one place rather than being
     * repeated in each document.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_shows_the_configured_contact_address(string $path): void
    {
        config(['site.support_email' => 'support@example.test']);

        $this->get($path)
            ->assertOk()
            ->assertSee('support@example.test');
    }

    public function test_the_legal_pages_name_the_full_operator_address(): void
    {
        config(['site.operator.address' => 'Some Street 1, Skopje, North Macedonia']);

        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('Some Street 1, Skopje, North Macedonia');

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Some Street 1, Skopje, North Macedonia');
    }

    /**
     * The Terms name the contracting party and the Privacy Policy names the data
     * controller. They must be the same party, and that party must match the
     * account holder at AgentaOS — one identity on the site against another on the
     * application is the mismatch their review looks for.
     */
    public function test_the_legal_pages_name_the_same_operator(): void
    {
        config(['site.operator.name' => 'Some Operator Name']);

        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('Some Operator Name');

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Some Operator Name');
    }

    /**
     * The exclusive-jurisdiction clause is unenforceable against an EU or UK
     * consumer regardless of what the contract says, so asserting it without a
     * carve-out gained us nothing and read as consumer-hostile to anyone
     * assessing whether we are a safe merchant to underwrite.
     */
    public function test_the_terms_preserve_mandatory_consumer_rights(): void
    {
        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('country of residence')
            ->assertSee('European Union or the United')
            ->assertSee('that law wins');
    }

    /**
     * Switched off when the credit pointed at a company while the legal pages named
     * an individual. Now that the company is the operator the line is redundant
     * rather than confusing — the footer would credit the party the Terms already
     * name. Still off; the config supports switching it back on.
     */
    #[DataProvider('publicPageProvider')]
    public function test_a_public_page_shows_no_powered_by_credit_by_default(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertDontSee('Powered by');
    }

    public function test_the_powered_by_credit_returns_when_a_url_is_configured(): void
    {
        config([
            'site.credit.name' => 'Some Studio',
            'site.credit.url' => 'https://studio.example',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Powered by')
            ->assertSee('Some Studio');
    }

    /**
     * The literal name is deliberate here rather than read from config, which
     * `test_the_legal_pages_name_the_same_operator` already covers. This asserts
     * the shipped default itself, because the operator identity moved once — from
     * an individual to the company — and it has to match the AgentaOS account
     * holder to survive their review. The legal form is part of the registered
     * name, not decoration.
     */
    public function test_the_terms_name_the_operator_as_the_contracting_party(): void
    {
        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertSee('Mayst Impact DOOEL')
            ->assertSee('trading as');
    }

    /**
     * The pages described the operator as an individual for as long as the plan was
     * to apply as one. Naming a registered company while still calling it a person
     * is the kind of leftover that reads as a copied template.
     */
    public function test_the_legal_pages_do_not_describe_the_operator_as_a_person(): void
    {
        foreach (['/terms-and-conditions', '/privacy-policy'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('as an individual')
                ->assertDontSee('the person legally answerable');
        }
    }

    /**
     * The tax number is the company's registry number. It is rendered
     * conditionally because it is not something to invent when absent, and the
     * row was genuinely absent while the operator was an individual.
     */
    public function test_the_legal_pages_show_a_tax_number_only_when_one_is_configured(): void
    {
        config(['site.operator.tax_id' => null]);

        $this->get('/terms-and-conditions')->assertOk()->assertDontSee('Tax number');
        $this->get('/privacy-policy')->assertOk()->assertDontSee('Tax number');

        config(['site.operator.tax_id' => 'MK4080012345678']);

        $this->get('/terms-and-conditions')->assertOk()->assertSee('MK4080012345678');
        $this->get('/privacy-policy')->assertOk()->assertSee('MK4080012345678');
    }

    /**
     * This address is the legal notice address cited in both the Terms and the
     * Privacy Policy, the only contact route on the site, and where the Refund
     * Policy promises a reply within two business days. A free mailbox there is a
     * trust signal in the wrong direction for a paid service under a merchant of
     * record review.
     */
    public function test_the_support_address_is_on_our_own_domain(): void
    {
        $email = config('site.support_email');

        $this->assertStringEndsWith('@'.config('site.domain'), $email);
        $this->assertStringNotContainsString('gmail.com', $email);
    }

    public function test_the_privacy_policy_names_the_data_controller(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Mayst Impact DOOEL')
            ->assertSee('data controller');
    }

    /**
     * The policy renders config('site.processors') rather than repeating the list
     * in prose, so a processor cannot be added to the stack without appearing in
     * the document. This is the mechanism that keeps the disclosure honest after
     * we stop thinking about it.
     */
    public function test_the_privacy_policy_names_every_processor(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();

        foreach (config('site.processors') as $processor) {
            $response->assertSee($processor['name']);
            $response->assertSee($processor['role'], false);
        }
    }

    public function test_the_privacy_policy_names_the_processors_we_actually_use(): void
    {
        $names = array_column(config('site.processors'), 'name');

        $this->assertContains('Laravel Cloud', $names);
        $this->assertContains('Cloudflare', $names);
        $this->assertContains('Resend', $names);
        $this->assertContains('AgentaOS', $names);
    }

    /**
     * The policy used to say data was "stored and processed in The Republic of
     * North Macedonia". Hosting is Laravel Cloud in the United States, so the one
     * paragraph about international transfers said the opposite of what happens.
     */
    public function test_the_privacy_policy_states_the_real_hosting_country(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('United States')
            ->assertDontSee('processed in The Republic of North Macedonia');
    }

    /**
     * Neither the United States nor North Macedonia is covered by an adequacy
     * decision that applies to us, so a transfer mechanism has to be named.
     */
    public function test_the_privacy_policy_names_the_transfer_mechanism(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Standard Contractual')
            ->assertSee('European Economic Area');
    }

    public function test_the_privacy_policy_states_a_legal_basis_for_each_purpose(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('Performance of a contract')
            ->assertSee('Legitimate interest')
            ->assertSee('Legal obligation')
            ->assertSee('Consent, which you may withdraw');
    }

    /**
     * People who scan a code are third parties with no account who never saw this
     * policy. What we record about them needs its own section, addressed to them.
     */
    public function test_the_privacy_policy_explains_what_a_scan_records(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('QR Code Scan Data')
            ->assertSee('approximate country')
            ->assertSee('visible to the person who created the code');
    }

    public function test_the_privacy_policy_explains_the_right_to_complain_to_a_supervisory_authority(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('supervisory')
            ->assertSee('lodge a complaint');
    }

    public function test_the_privacy_policy_states_we_never_hold_card_details(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('merchant of record')
            ->assertSee('never see or store');
    }

    public function test_the_privacy_policy_and_terms_agree_on_the_minimum_age(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('at least 18 years old');
        $this->get('/terms-and-conditions')->assertOk()->assertSee('at least 18 years old');
    }

    /**
     * The retention table renders config('session.lifetime') rather than a written
     * number. The policy said nothing about sessions before, and a hardcoded "2
     * weeks" would have been wrong — the real lifetime is 120 minutes.
     */
    public function test_the_privacy_policy_states_the_real_session_lifetime(): void
    {
        config(['session.lifetime' => 45]);

        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('45 minutes of inactivity');
    }

    /**
     * Section 2 used to describe dynamic codes as having a 7-day validity that
     * you extended by buying a package. That product was retired; section 5
     * already described the subscription, so the document contradicted itself.
     */
    public function test_the_terms_do_not_describe_the_retired_extension_packages(): void
    {
        $this->get('/terms-and-conditions')
            ->assertOk()
            ->assertDontSee('extension package')
            ->assertDontSee('7-day validity')
            ->assertSee('Static QR codes');
    }
}
