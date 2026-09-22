<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment of a work schedule to an employee, over a date range.
 *
 * Changing someone's schedule never edits a row: the current assignment is
 * closed (effective_to = the day before) and a new one is inserted. That keeps
 * "which schedule was this person on last March?" answerable, and gives
 * temporary schedule changes for free — an assignment with both ends set.
 *
 * effective_to = null means open-ended, i.e. the current assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Restrict: a schedule still assigned to someone cannot be deleted.
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
