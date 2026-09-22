<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where the employee was *due* to work, beside where they actually did.
 *
 * `location` used to be a snapshot of the week's template and nothing else.
 * Now that an employee can say "today I'm working from home" at check-in,
 * `location` becomes where the day was actually worked, and this column keeps
 * what the schedule expected — so a one-off exception is still visible as an
 * exception months later, after the template itself has changed.
 *
 * Deriving it at read time was the alternative and it is wrong for the same
 * reason every other schedule field is snapshotted: re-reading today's
 * template would rewrite the past.
 *
 * Nullable, like `location`: a day off expects no work anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('scheduled_location', 10)->nullable()->after('location');
        });

        // Every existing row was taken straight from the template, so what was
        // scheduled is exactly what was recorded — none of them are exceptions.
        DB::table('attendances')
            ->whereNotNull('location')
            ->update(['scheduled_location' => DB::raw('location')]);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('scheduled_location');
        });
    }
};
