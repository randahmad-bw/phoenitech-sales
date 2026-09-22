<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add pricing & boost cost columns to sm_packages,
 * make contract_id nullable so packages can be global templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_packages', function (Blueprint $table) {
            // Drop foreign key first so MySQL allows dropping the unique index
            $table->dropForeign(['contract_id']);
            $table->dropUnique(['contract_id']);

            // Make contract_id nullable
            $table->unsignedBigInteger('contract_id')->nullable()->change();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();

            // Pricing
            $table->decimal('price', 10, 2)->nullable()->after('package_name');

            // Boost / Sponsorship costs per content type
            $table->decimal('boost_reel_cost', 10, 2)->nullable()->after('monthly_stories');
            $table->decimal('boost_post_cost', 10, 2)->nullable()->after('boost_reel_cost');
            $table->decimal('boost_story_cost', 10, 2)->nullable()->after('boost_post_cost');

            // Flag to distinguish custom packages from predefined ones
            $table->boolean('is_custom')->default(false)->after('boost_story_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sm_packages', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'boost_reel_cost',
                'boost_post_cost',
                'boost_story_cost',
                'is_custom',
            ]);
        });
    }
};
