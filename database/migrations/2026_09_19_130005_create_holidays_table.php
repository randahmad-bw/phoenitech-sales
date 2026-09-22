<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company and public holidays — days nobody is expected to work.
 *
 * A holiday is never an absence. `attendance:close-day` skips these dates, and
 * the reader renders them as status `holiday`.
 *
 * `is_recurring` marks a fixed-date annual holiday (e.g. 01-01): the year on
 * `date` is then only the year it was first recorded, and matching ignores it.
 * Moveable feasts are entered per year with is_recurring = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_recurring')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('date');
            $table->index('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
