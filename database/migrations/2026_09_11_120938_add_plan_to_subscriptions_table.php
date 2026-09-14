<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Plan a buyer chose at checkout, so the provisional grant when the
 * payment lands is one period of what they bought and not of a default.
 *
 * Nullable with no default and no backfill: the table is empty at the time of
 * writing, and a row that reaches the grant job without a Plan is an anomaly
 * worth being able to see rather than one silently relabelled as the default.
 * See docs/adr/0003.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan', 16)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
