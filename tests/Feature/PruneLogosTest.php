<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A logo is uploaded before the record that holds it exists, so the dashboard
 * can leave a file behind that nothing refers to. Unreferenced is not the same
 * as abandoned: the grace period protects an upload whose form is still open.
 */
class PruneLogosTest extends TestCase
{
    use RefreshDatabase;

    private const LOGO = 'qr-logos/logo.png';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Storage::put(self::LOGO, 'png-bytes');
    }

    public function test_an_abandoned_upload_is_pruned_once_the_grace_period_has_passed(): void
    {
        $this->travel(49)->hours();

        $this->artisan('logos:prune')->assertSuccessful();

        $this->assertSame([], Storage::files('qr-logos'));
    }

    public function test_a_logo_in_an_owners_own_directory_is_pruned_when_abandoned_and_kept_when_used(): void
    {
        $user = User::factory()->create();
        $used = "qr-logos/{$user->id}/used.png";
        $abandoned = "qr-logos/{$user->id}/abandoned.png";
        Storage::put($used, 'png-bytes');
        Storage::put($abandoned, 'png-bytes');
        QrCode::factory()->for($user)->create([
            'options' => ['design' => ['version' => 1, 'logo' => ['path' => $used]]],
        ]);

        $this->travel(100)->hours();

        $this->artisan('logos:prune')->assertSuccessful();

        Storage::assertExists($used);
        Storage::assertMissing($abandoned);
    }

    public function test_an_upload_inside_the_grace_period_is_left_alone(): void
    {
        $this->travel(47)->hours();

        $this->artisan('logos:prune')->assertSuccessful();

        $this->assertCount(1, Storage::files('qr-logos'));
    }

    public function test_a_logo_a_record_still_refers_to_is_never_pruned(): void
    {
        QrCode::factory()->for(User::factory())->create([
            'options' => ['design' => ['version' => 1, 'logo' => ['path' => self::LOGO]]],
        ]);

        $this->travel(100)->hours();

        $this->artisan('logos:prune')->assertSuccessful();

        $this->assertTrue(Storage::exists(self::LOGO));
    }

    public function test_a_dry_run_reports_without_deleting(): void
    {
        $this->travel(49)->hours();

        $this->artisan('logos:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertCount(1, Storage::files('qr-logos'));
    }

    public function test_a_grace_period_below_an_hour_refuses_to_prune(): void
    {
        $this->travel(1000)->hours();
        config(['site.orphan_logo_grace_hours' => 0]);

        $this->artisan('logos:prune')->assertFailed();

        $this->assertCount(1, Storage::files('qr-logos'));
    }
}
