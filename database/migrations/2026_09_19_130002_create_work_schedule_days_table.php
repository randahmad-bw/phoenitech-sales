<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The working days of a schedule — one row per working weekday.
 *
 * A weekday with NO row is simply not a working day. Nothing about the working
 * week is hard-coded anywhere in the system: one employee's week may start on
 * Saturday and another's on Sunday, and both are expressed here.
 *
 * `weekday` uses Carbon's native numbering (0 = Sunday … 6 = Saturday) so no
 * conversion is ever needed when comparing against a date. The order days are
 * *displayed* in is a separate, presentational concern driven by
 * config('attendance.week_start').
 *
 * `sort_order` is the extension point for split shifts: a second row for the
 * same weekday (sort_order = 1) becomes the afternoon half. V1 always writes 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            // Derived on save from start/end/break — snapshotted onto each
            // attendance row so later edits cannot rewrite past expectations.
            $table->unsignedSmallInteger('expected_minutes')->default(0);
            $table->timestamps();

            $table->unique(['work_schedule_id', 'weekday', 'sort_order'], 'work_schedule_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};
