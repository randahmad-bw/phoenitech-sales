<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two facts the first design did not carry.
 *
 * 1. **Where the day is worked.** Location is per *day*, not per employee: one
 *    person is in the office Saturday and Sunday and at home Monday to
 *    Wednesday. Putting it on the employee would make that unrepresentable.
 *
 * 2. **An open-ended day.** Some people start at a fixed hour and leave when
 *    the work is done. `end_time` becomes nullable to mean exactly that —
 *    there is no scheduled end, so `expected_minutes` is 0 and nothing is
 *    measured against. The hours actually worked are still recorded in full.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->string('location', 10)->default('office')->after('sort_order');
        });

        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        Schema::table('work_schedule_days', function (Blueprint $table) {
            $table->time('end_time')->nullable(false)->change();
        });
    }
};
