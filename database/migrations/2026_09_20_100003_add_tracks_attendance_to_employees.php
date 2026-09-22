<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not everyone is on the attendance system.
 *
 * Management is deliberately outside it, and sales work by field visits with
 * no fixed hours or place — recording a check-in for them would be inventing
 * a rule nobody follows.
 *
 * This is kept separate from "has no schedule yet". Without the flag both
 * cases look identical, and the dashboard would keep prompting management to
 * configure people who are never meant to be configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('tracks_attendance')->default(true)->after('employment_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tracks_attendance');
        });
    }
};
