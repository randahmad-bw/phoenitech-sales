<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the day's work location onto the attendance row.
 *
 * Same reason every other schedule field is snapshotted: moving someone from
 * office to remote next month must not rewrite where they worked last month.
 *
 * Nullable rather than defaulted, because a day off has no location at all —
 * and "office" would be a small lie on a row nobody was expected to work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('location', 10)->nullable()->after('scheduled_end');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
