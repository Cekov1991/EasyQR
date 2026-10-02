<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use App\Models\QrCode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The name, the kind of code and what it encodes share one card at the top of
 * the form, so the Design editor below starts higher up. Moving them together
 * must not change when each one can be set.
 */
class QrCodeFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_new_code_offers_its_name_type_and_content_type_together(): void
    {
        Livewire::actingAs(User::factory()->create())->test(CreateQrCode::class)
            ->assertOk()
            ->assertSeeInOrder(['Basic Information', 'Name', 'Type', 'QR Code Type'])
            ->assertFormFieldIsVisible('qr_content_type')
            ->assertFormFieldIsEnabled('qr_content_type');
    }

    public function test_a_static_code_being_edited_hides_what_it_encodes(): void
    {
        $code = QrCode::factory()->for(User::factory()->create())->create();

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->assertOk()
            ->assertFormFieldIsHidden('qr_content_type');
    }

    public function test_a_dynamic_code_being_edited_can_still_change_what_it_encodes(): void
    {
        $code = QrCode::factory()->dynamic()->for(User::factory()->create())->create();

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->assertOk()
            ->assertFormFieldIsVisible('qr_content_type')
            ->assertFormFieldIsEnabled('qr_content_type');
    }
}
