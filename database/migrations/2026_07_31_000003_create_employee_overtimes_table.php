<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_overtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('overtime_date');
            $table->decimal('hours', 4, 2)->default(0);
            $table->decimal('days_equivalent', 4, 2)->default(0);
            $table->decimal('rate_multiplier', 3, 2)->default(1.50);
            $table->enum('overtime_type', ['workday', 'weekend', 'holiday'])->default('workday');
            $table->string('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtimes');
    }
};
