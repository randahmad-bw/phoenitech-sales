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
            $table->foreignId('parent_contract_id')->nullable()->after('id')->constrained('contracts')->nullOnDelete();
        });

        // Alter enum to include 'renewed'
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('draft', 'signed', 'active', 'completed', 'cancelled', 'suspended', 'renewed') DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum back (might lose 'renewed' statuses, so we should convert them to 'completed' or something before)
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("UPDATE contracts SET status = 'completed' WHERE status = 'renewed'");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE contracts MODIFY COLUMN status ENUM('draft', 'signed', 'active', 'completed', 'cancelled', 'suspended') DEFAULT 'draft'");
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['parent_contract_id']);
            $table->dropColumn('parent_contract_id');
        });
    }
};
