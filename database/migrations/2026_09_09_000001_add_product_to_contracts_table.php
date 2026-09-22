<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add product/brand column to contracts table.
 * Allows distinguishing between PhoeniTech and OnoCode contracts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('product', ['phoenitech', 'onocode', 'other'])
                ->default('phoenitech')
                ->after('category_custom');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('product');
        });
    }
};
