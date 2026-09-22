<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work schedule templates — "Office 09:00-17:00", "Evening shift", ...
 *
 * A template carries no working days itself; those live in work_schedule_days.
 * Employees are attached to a template through employee_schedules, never
 * directly, so one template can serve many people and a person's schedule can
 * change over time without rewriting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
