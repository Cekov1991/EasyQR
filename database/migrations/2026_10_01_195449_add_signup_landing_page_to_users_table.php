<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Landing Page the link in `signup_source` sat on, if it sat on one.
 *
 * The second of the two facts in ADR-0004, kept apart from the source so the
 * offer-against-inline comparison still holds on every page and the enum does
 * not grow a case per page.
 *
 * Nullable, and null is the normal case: every registration from the homepage
 * or from nowhere in particular. Values are constrained by the registry of
 * published Landing Pages rather than by the database, so publishing a page
 * needs no migration. Like `signup_source`, it is not in the model's
 * $fillable and has exactly one write site, which re-validates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('signup_landing_page')->nullable()->after('signup_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('signup_landing_page');
        });
    }
};
