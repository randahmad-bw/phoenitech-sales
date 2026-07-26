<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social Media Management Module — Simplified Schema with Two-Stage Checkboxes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop tables if exist
        Schema::dropIfExists('sm_content_items');
        Schema::dropIfExists('sm_photo_sessions');
        Schema::dropIfExists('sm_content_plans');
        Schema::dropIfExists('sm_packages');

        // 1. SM PACKAGES — Monthly content quotas linked to contracts
        Schema::create('sm_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('contracts')->cascadeOnDelete();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('monthly_posts')->default(6);
            $table->unsignedInteger('monthly_reels')->default(6);
            $table->unsignedInteger('monthly_stories')->default(12);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. SM CONTENT PLANS — Monthly plan per contract
        Schema::create('sm_content_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('sm_package_id')->nullable()->constrained('sm_packages')->nullOnDelete();
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedSmallInteger('year');
            $table->string('status', 20)->default('active'); // active | completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'month', 'year']);
        });

        // 3. SM PHOTO SESSIONS — Photography/video sessions
        Schema::create('sm_photo_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained('sm_content_plans')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('session_date');
            $table->time('session_time');
            $table->foreignId('photographer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('scheduled'); // scheduled | completed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. SM CONTENT ITEMS — Individual content pieces with 2 checkboxes
        Schema::create('sm_content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained('sm_content_plans')->cascadeOnDelete();
            $table->string('title');
            $table->string('content_type', 20); // post | reel | story
            $table->date('design_date')->nullable(); // تاريخ التصميم / التصوير
            $table->date('publish_date')->nullable(); // تاريخ النشر
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('photo_session_id')->nullable()->constrained('sm_photo_sessions')->nullOnDelete();
            $table->boolean('is_designed')->default(false); // Checkbox 1: اتصممت / اتصورت
            $table->boolean('is_published')->default(false); // Checkbox 2: انتشرت على السوشال
            $table->string('status', 20)->default('pending'); // pending | in_progress | completed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['content_plan_id', 'is_published']);
            $table->index(['publish_date', 'is_published']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_content_items');
        Schema::dropIfExists('sm_photo_sessions');
        Schema::dropIfExists('sm_content_plans');
        Schema::dropIfExists('sm_packages');
    }
};
