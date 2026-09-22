<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `employee_leaves.leave_type` stops being an enum.
 *
 * An enum column is a second copy of the list of types, kept in the schema.
 * With the list now living in `leave_types`, a type added from the settings
 * screen would be rejected by the database on insert — so the column becomes a
 * plain string that names a row in that table.
 *
 * Deliberately **not** a foreign key. A type may be deleted only while nothing
 * uses it, which the service enforces, and a leave record must survive a type
 * being renamed or removed by hand in the database rather than cascade into
 * nothing. The column is a label, and the history it belongs to outlives the
 * catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->string('leave_type', 40)->default('annual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->enum('leave_type', ['annual', 'sick', 'unpaid', 'emergency', 'special'])
                ->default('annual')
                ->change();
        });
    }
};
