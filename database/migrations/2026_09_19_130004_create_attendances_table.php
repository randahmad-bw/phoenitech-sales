<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily attendance record: when someone checked in, when they checked out,
 * and the hours between. Deliberately NOT a judgment.
 *
 * There is no lateness, early-leave, grace period or overtime column. The
 * system records facts; management reads them. Because both the actual times
 * and the schedule-as-it-was-that-day live on the row, any of those rules can
 * be switched on later and applied retroactively to historical rows — nothing
 * is lost by leaving them out now. That is precisely why the scheduled_* and
 * expected_minutes snapshots are kept.
 *
 * The snapshot also protects history the other way round: editing (or deleting)
 * a work schedule template can never rewrite what a past day was expected to be.
 *
 * `work_date` is the date in the company timezone (config('attendance.timezone')),
 * NOT in UTC — otherwise a 22:00 Damascus check-in would land on the next day.
 * The two datetimes are stored as ordinary UTC timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            // present | absent | incomplete | day_off | holiday | leave
            $table->string('status', 20)->default('present');

            // --- snapshot of the schedule in force on this date ---
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->unsignedSmallInteger('expected_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);

            // --- the one computed fact ---
            // (check_out_at - check_in_at) - break_minutes
            $table->unsignedInteger('worked_minutes')->default(0);

            // --- provenance & corrections ---
            // self   = the employee pressed the button
            // manual = entered or corrected by management
            // system = written by attendance:close-day
            $table->string('source', 10)->default('self');
            $table->string('correction_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The real guarantee against double check-in and concurrent
            // requests — one row per employee per day, enforced by the database.
            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
