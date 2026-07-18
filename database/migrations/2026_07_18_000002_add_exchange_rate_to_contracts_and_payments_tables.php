<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->default(1.0)->after('currency');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->default(1.0)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
