<?php

namespace Tests\Feature\Subscription;

use App\Filament\Resources\QrCodeResource;
use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Models\QrCode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class QrCodeCreationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $type): array
    {
        return [
            'name' => 'Test code',
            'type' => $type,
            'qr_content_type' => 'website',
            'qr_content_data' => ['url' => 'https://example.com'],
        ];
    }

    public function test_a_trialing_user_can_create_a_dynamic_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->formData('dynamic'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $user->qrCodes()->where('type', 'dynamic')->count());
    }

    public function test_a_lapsed_user_cannot_create_a_dynamic_code(): void
    {
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->formData('dynamic'))
            ->call('create')
            ->assertNotified();

        $this->assertSame(0, $user->qrCodes()->count());
    }

    public function test_a_lapsed_user_can_still_create_a_static_code(): void
    {
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->formData('static'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $user->qrCodes()->where('type', 'static')->count());
    }

    public function test_creation_is_refused_once_the_dynamic_quota_is_used_up(): void
    {
        $user = User::factory()->create(['dynamic_qr_limit' => 1]);
        QrCode::factory()->for($user)->dynamic()->create();

        $this->actingAs($user);

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->formData('dynamic'))
            ->call('create')
            ->assertNotified();

        $this->assertSame(1, $user->qrCodes()->where('type', 'dynamic')->count());
    }

    public function test_the_dynamic_option_is_hidden_from_a_lapsed_user(): void
    {
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        $this->assertSame(['static'], array_keys(QrCodeResource::typeOptions()));
        $this->assertStringContainsString(
            'active subscription',
            QrCodeResource::dynamicUnavailableReason(),
        );
    }

    public function test_an_existing_record_always_offers_its_own_type(): void
    {
        // A lapsed owner editing a dynamic code must not see a blank select.
        $user = User::factory()->create();
        $this->travel(8)->days();
        $this->actingAs($user);

        $record = QrCode::factory()->for($user)->dynamic()->create();

        $this->assertArrayHasKey('dynamic', QrCodeResource::typeOptions($record));
    }

    public function test_the_quota_message_names_the_limit_when_it_is_exhausted(): void
    {
        $user = User::factory()->create(['dynamic_qr_limit' => 3]);
        QrCode::factory()->count(3)->for($user)->dynamic()->create();

        $this->actingAs($user);

        $this->assertStringContainsString('all 3', QrCodeResource::dynamicUnavailableReason());
    }

    public function test_the_full_quota_notice_offers_the_larger_plan(): void
    {
        $user = User::factory()->create();
        QrCode::factory()->count(5)->for($user)->dynamic()->create();

        $this->actingAs($user);

        Livewire::test(CreateQrCode::class)
            ->fillForm($this->formData('dynamic'))
            ->call('create')
            ->assertNotified('QR code limit reached');

        $message = QrCodeResource::dynamicLimitReachedMessage($user);

        $this->assertStringContainsString('all 5', $message);
        $this->assertStringContainsString('25 dynamic codes for $99 a year', $message);
        $this->assertStringContainsString(config('site.support_email'), $message);
    }

    /**
     * An account already raised to the larger ceiling has nothing left to buy,
     * and offering it the plan it is on reads as a billing mistake.
     */
    public function test_the_full_quota_notice_does_not_offer_the_larger_plan_to_an_account_already_on_it(): void
    {
        $user = User::factory()->create(['dynamic_qr_limit' => 25]);

        $message = QrCodeResource::dynamicLimitReachedMessage($user);

        $this->assertStringContainsString('all 25', $message);
        $this->assertStringNotContainsString('larger plan', $message);
    }

    public function test_the_create_button_disappears_only_when_both_quotas_are_gone(): void
    {
        $user = User::factory()->create([
            'dynamic_qr_limit' => 1,
            'static_qr_limit' => 1,
        ]);
        $this->actingAs($user);

        $this->assertTrue(QrCodeResource::canCreate());

        QrCode::factory()->for($user)->dynamic()->create();
        QrCode::factory()->for($user)->create();

        $this->assertFalse(QrCodeResource::canCreate());
    }
}
