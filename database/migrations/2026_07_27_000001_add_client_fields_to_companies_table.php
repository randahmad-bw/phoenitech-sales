<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add client_name and phone fields to companies table.
 * client_name = contact person / client representative name.
 * phone = primary phone number for the company or client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('name');
            $table->string('phone', 50)->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'phone']);
        });
    }
};
