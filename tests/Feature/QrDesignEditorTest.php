<?php

namespace Tests\Feature;

use App\Filament\Resources\QrCodeResource\Pages\CreateQrCode;
use App\Filament\Resources\QrCodeResource\Pages\EditQrCode;
use App\Models\QrCode;
use App\Models\User;
use App\Support\QrDesignOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Design editor on the create and edit forms (ADR-0005): the form's state is
 * the Design, the server validates it whatever the browser did, and saving
 * stores the Design and writes no image.
 */
class QrDesignEditorTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.com/menu';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function design(array $changes = []): array
    {
        return [...QrDesignOptions::defaultDesign(), ...$changes];
    }

    /**
     * @return array<string, mixed>
     */
    private function logo(array $changes = []): array
    {
        return [
            'path' => 'qr-logos/logo.png',
            'shape' => 'rounded',
            'size' => 20,
            'padding' => 2,
            'backing' => true,
            'clearSpace' => true,
            ...$changes,
        ];
    }

    private function createForm(?User $user = null): Testable
    {
        return Livewire::actingAs($user ?? User::factory()->create())->test(CreateQrCode::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(Testable $page, array $data = []): Testable
    {
        return $page
            ->fillForm([
                'name' => 'Menu',
                'type' => 'static',
                'qr_content_type' => 'website',
                'qr_content_data' => ['url' => self::URL],
                ...$data,
            ])
            ->call('create');
    }

    public function test_a_new_code_starts_in_the_rounded_look(): void
    {
        $this->createForm()->assertFormSet(['options.design' => QrDesignOptions::defaultDesign()]);

        $this->assertSame('fluid', QrDesignOptions::defaultDesign()['dot']);
    }

    public function test_the_old_appearance_fields_are_gone_from_the_form(): void
    {
        $page = $this->createForm();

        foreach (['style', 'format', 'size', 'errorCorrection', 'color'] as $old) {
            $page->assertFormFieldDoesNotExist('options.'.$old);
        }

        $page->assertFormFieldExists('options.design');
    }

    public function test_creating_stores_the_design_and_writes_no_image(): void
    {
        $design = $this->design(['dot' => 'dots', 'codeColor' => '#233a83', 'frame' => 'badge', 'frameText' => 'SCAN ME']);
        $user = User::factory()->create();

        $this->create($this->createForm($user), ['options' => ['design' => $design]])->assertHasNoFormErrors();

        $stored = $user->qrCodes()->sole();

        $this->assertSame($design, $stored->options['design']);
        $this->assertSame(1, $stored->options['design']['version']);
        $this->assertNull($stored->qr_code_image);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_every_look_can_be_chosen_and_is_kept(): void
    {
        $user = User::factory()->create();

        foreach (array_keys(QrDesignOptions::all()['looks']) as $look) {
            $settings = QrDesignOptions::all()['looks'][$look];
            unset($settings['label']);
            $design = $this->design($settings);

            $this->create($this->createForm($user), ['name' => "Code in {$look}", 'options' => ['design' => $design]])
                ->assertHasNoFormErrors();

            $this->assertSame($design, $user->qrCodes()->where('name', "Code in {$look}")->sole()->options['design']);
        }
    }

    public function test_a_code_saved_without_touching_the_editor_is_rounded(): void
    {
        $user = User::factory()->create();

        $this->create($this->createForm($user))->assertHasNoFormErrors();

        $this->assertSame(QrDesignOptions::defaultDesign(), $user->qrCodes()->sole()->options['design']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function refusedDesigns(): array
    {
        return [
            'a module shape outside the set' => [['dot' => 'hexagon'], 'module shape'],
            'a corner outside the set' => [['corner' => 'star'], 'corner'],
            'an eye outside the set' => [['eye' => 'cross'], 'eye'],
            'a frame outside the set' => [['frame' => 'ribbon'], 'frame'],
            'a colour that is not hex' => [['codeColor' => 'red'], 'code colour'],
            'a short hex colour' => [['bgColor' => '#fff'], 'background colour'],
            'a missing eye colour' => [['eyeColor' => null], 'eye colour'],
            'frame text that is too long' => [['frameText' => str_repeat('a', 19)], '18 characters'],
            'frame text that is not text' => [['frameText' => ['x']], 'Frame text'],
            'another version' => [['version' => 2], 'version'],
            'a missing version' => [['version' => null], 'version'],
            'code and background too close' => [['codeColor' => '#777777', 'bgColor' => '#888888'], 'too close in colour'],
            'contrast just under 3 to 1' => [['codeColor' => '#959595', 'bgColor' => '#ffffff'], 'too close in colour'],
            'a logo too small' => [['logo' => ['size' => 9]], 'size'],
            'a logo too big' => [['logo' => ['size' => 26]], 'size'],
            'padding too big' => [['logo' => ['padding' => 5.5]], 'padding'],
            'padding off the half steps' => [['logo' => ['padding' => 1.3]], 'padding'],
            'a hidden box wider than a quarter' => [['logo' => ['size' => 25, 'padding' => 1]], '25%'],
            'a hidden box over a quarter with only clear space' => [['logo' => ['size' => 22, 'padding' => 2, 'backing' => false]], '25%'],
            'a logo shape outside the set' => [['logo' => ['shape' => 'star']], 'logo shape'],
            'a logo without a file' => [['logo' => ['path' => '']], 'logo file'],
            'a logo switch that is not a switch' => [['logo' => ['backing' => 'yes']], 'backing'],
            'a setting nobody defined' => [['gradient' => 'linear'], 'gradient'],
            'a design that is not a design' => [['__replace' => 'a string'], 'Design'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('refusedDesigns')]
    public function test_the_server_refuses_a_design_the_browser_should_never_send(array $changes, string $reason): void
    {
        $user = User::factory()->create();

        if (isset($changes['logo'])) {
            $changes['logo'] = $this->logo($changes['logo']);
        }

        $design = isset($changes['__replace']) ? $changes['__replace'] : $this->design($changes);

        $page = $this->create($this->createForm($user), ['options' => ['design' => $design]]);

        $page->assertHasFormErrors(['options.design']);
        $this->assertStringContainsStringIgnoringCase($reason, implode(' ', $page->errors()->get('data.options.design')));
        $this->assertSame(0, $user->qrCodes()->count());
    }

    public function test_every_message_a_refusal_gives_is_a_sentence_for_a_person(): void
    {
        $page = $this->create($this->createForm(), ['options' => ['design' => $this->design(['codeColor' => '#777777', 'bgColor' => '#888888', 'frame' => 'ribbon'])]]);

        $messages = $page->errors()->get('data.options.design');

        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertMatchesRegularExpression('/^[A-Z].*\.$/', $message);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function acceptedDesigns(): array
    {
        return [
            'contrast just over 3 to 1' => [['codeColor' => '#949494', 'bgColor' => '#ffffff']],
            'light on dark, which only warns' => [['codeColor' => '#ffffff', 'bgColor' => '#111111']],
            'a hidden box of exactly a quarter' => [['logo' => ['size' => 20, 'padding' => 2.5]]],
            'the largest logo with no padding' => [['logo' => ['size' => 25, 'padding' => 0]]],
            'a big logo that hides nothing extra' => [['logo' => ['size' => 25, 'padding' => 3, 'backing' => false, 'clearSpace' => false]]],
            'upper case hex' => [['codeColor' => '#171B19']],
            'empty frame text' => [['frame' => 'label', 'frameText' => '']],
            'frame text of exactly 18' => [['frame' => 'badge', 'frameText' => str_repeat('é', 18)]],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('acceptedDesigns')]
    public function test_the_server_accepts_every_design_the_controls_can_reach(array $changes): void
    {
        $user = User::factory()->create();

        if (isset($changes['logo'])) {
            $changes['logo'] = $this->logo($changes['logo']);
        }

        $this->create($this->createForm($user), ['options' => ['design' => $this->design($changes)]])
            ->assertHasNoFormErrors();

        $this->assertSame(1, $user->qrCodes()->count());
    }

    public function test_editing_restyles_a_static_code_and_keeps_what_it_encodes(): void
    {
        $code = QrCode::factory()->create(['qr_content_data' => ['url' => self::URL]]);
        $image = $code->qr_code_image;
        $design = $this->design(['dot' => 'square', 'frame' => 'border']);

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->assertFormSet(['options.design' => QrDesignOptions::defaultDesign()])
            ->fillForm(['options' => ['design' => $design]])
            ->call('save')
            ->assertHasNoFormErrors();

        $code->refresh();

        $this->assertSame($design, $code->options['design']);
        $this->assertSame(self::URL, $code->encodedContent());
        $this->assertSame($image, $code->qr_code_image, 'Restyling must not redraw a stored image.');
    }

    public function test_the_editor_opens_on_the_design_a_code_was_saved_with(): void
    {
        $design = $this->design(['dot' => 'dots', 'frame' => 'badge']);
        $code = QrCode::factory()->create(['options' => ['design' => $design]]);

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->assertFormSet(['options.design' => $design]);
    }

    public function test_editing_refuses_a_design_that_would_not_scan(): void
    {
        $code = QrCode::factory()->create();

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->fillForm(['options' => ['design' => $this->design(['codeColor' => '#777777', 'bgColor' => '#888888'])]])
            ->call('save')
            ->assertHasFormErrors(['options.design']);

        $this->assertArrayNotHasKey('design', $code->refresh()->options ?? []);
    }

    public function test_a_lapsed_owner_can_edit_a_static_codes_design(): void
    {
        $code = QrCode::factory()->create();
        $this->travel(8)->days();
        $this->assertTrue($code->user->isLapsed());

        $design = $this->design(['codeColor' => '#233a83', 'eye' => 'square']);

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->fillForm(['options' => ['design' => $design]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($design, $code->refresh()->options['design']);
    }

    public function test_a_dynamic_code_keeps_the_short_url_reserved_when_the_form_opened(): void
    {
        $user = User::factory()->create();
        $page = $this->createForm($user);

        $reserved = $page->get('data.short_url');
        $this->assertMatchesRegularExpression('/^[2-9a-km-zA-HJ-NP-Z]{8,}$/', $reserved);

        $this->create($page, ['type' => 'dynamic'])->assertHasNoFormErrors();

        $this->assertSame($reserved, $user->qrCodes()->sole()->short_url);
    }

    public function test_a_short_url_taken_while_the_form_was_open_is_replaced_and_the_owner_looks_again(): void
    {
        $user = User::factory()->create();
        $page = $this->createForm($user);
        $reserved = $page->get('data.short_url');
        QrCode::factory()->dynamic()->create(['short_url' => $reserved]);

        $this->create($page, ['type' => 'dynamic']);

        $this->assertSame(0, $user->qrCodes()->count());
        $this->assertNotSame($reserved, $page->get('data.short_url'));
        $page->assertNotified();

        $page->call('create')->assertHasNoFormErrors();
        $this->assertSame($page->get('data.short_url'), $user->qrCodes()->sole()->short_url);
    }

    public function test_a_tampered_short_url_is_refused(): void
    {
        $user = User::factory()->create();

        $this->create($this->createForm($user), ['type' => 'dynamic', 'short_url' => 'x'])
            ->assertHasFormErrors(['short_url']);

        $this->assertSame(0, $user->qrCodes()->count());
    }

    public function test_restyling_a_dynamic_code_never_changes_its_short_url(): void
    {
        $code = QrCode::factory()->dynamic()->create(['short_url' => 'aB3dE5gH']);
        $design = $this->design(['dot' => 'rounded']);

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->fillForm(['options' => ['design' => $design], 'short_url' => 'zzzzzzzz'])
            ->call('save')
            ->assertHasNoFormErrors();

        $code->refresh();

        $this->assertSame('aB3dE5gH', $code->short_url);
        $this->assertSame($design, $code->options['design']);
    }

    public function test_an_edit_previews_the_short_url_the_code_already_has(): void
    {
        $code = QrCode::factory()->dynamic()->create(['short_url' => 'aB3dE5gH']);

        Livewire::actingAs($code->user)->test(EditQrCode::class, ['record' => $code->getKey()])
            ->call('encodedContent')
            ->assertReturned(route('qr.redirect', 'aB3dE5gH'));
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function contentTypes(): array
    {
        return [
            'website' => ['website', ['url' => 'https://example.com/menu'], 'https://example.com/menu'],
            'wifi' => ['wifi', ['ssid' => 'Cafe', 'security' => 'WPA', 'password' => 'secret', 'hidden' => false], 'WIFI:T:WPA;S:Cafe;P:secret;H:false;;'],
            'email' => ['email', ['email' => 'hi@example.com', 'subject' => 'Hello there'], 'mailto:hi@example.com?subject=Hello+there'],
            'whatsapp' => ['whatsapp', ['phone' => '31612345678', 'message' => 'Hi'], 'https://wa.me/31612345678?text=Hi'],
            'vcard' => ['vcard', ['first_name' => 'Eva', 'last_name' => 'Jansen', 'phone' => '+31612345678'], "BEGIN:VCARD\nVERSION:3.0\nN:Jansen;Eva;;;\nFN:Eva Jansen\nTEL:+31612345678\nEND:VCARD"],
            'sms' => ['sms', ['phone' => '+31612345678', 'message' => 'Yes'], 'sms:+31612345678?body=Yes'],
            'phone' => ['phone', ['phone' => '+31612345678'], 'tel:+31612345678'],
            'calendar' => ['calendar', ['summary' => 'Opening', 'start_date' => '20261101T100000Z', 'end_date' => '20261101T120000Z'], "BEGIN:VEVENT\nSUMMARY:Opening\nDTSTART:20261101T100000Z\nDTEND:20261101T120000Z\nEND:VEVENT"],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[DataProvider('contentTypes')]
    public function test_each_content_type_produces_its_encoded_string_for_the_preview(string $type, array $data, string $encoded): void
    {
        $this->createForm()
            ->fillForm(['qr_content_type' => $type, 'qr_content_data' => $data])
            ->call('encodedContent')
            ->assertReturned($encoded);
    }

    public function test_the_preview_of_a_dynamic_code_encodes_its_short_url_whatever_the_content(): void
    {
        $page = $this->createForm();
        $reserved = $page->get('data.short_url');

        $page->fillForm(['type' => 'dynamic', 'qr_content_data' => ['url' => self::URL]])
            ->call('encodedContent')
            ->assertReturned(route('qr.redirect', $reserved));
    }

    public function test_an_unfinished_form_previews_nothing_rather_than_failing(): void
    {
        $this->createForm()
            ->fillForm(['qr_content_type' => 'website', 'qr_content_data' => []])
            ->call('encodedContent')
            ->assertReturned('');
    }
}
