<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('department');
            $table->decimal('base_salary', 12, 2)->nullable()->after('job_title');
            $table->integer('annual_leave_allowance')->default(21)->after('base_salary');
            $table->text('job_description')->nullable()->after('annual_leave_allowance');
            $table->json('responsibilities')->nullable()->after('job_description');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'base_salary',
                'annual_leave_allowance',
                'job_description',
                'responsibilities',
            ]);
        });
    }
};
