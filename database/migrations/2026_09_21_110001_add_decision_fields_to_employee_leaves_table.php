<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who decided a leave request, and when.
 *
 * `approved_by` already existed but nothing recorded *when* the decision was
 * made or why a request was turned down. A refusal that carries no reason is
 * the kind of thing people ask about later, and the record should answer.
 *
 * `datetime`, not `timestamp` — MariaDB attaches an implicit
 * `ON UPDATE CURRENT_TIMESTAMP` to the first NOT NULL TIMESTAMP column in a
 * table, which silently rewrites it on every later save. See docs §25.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->datetime('decided_at')->nullable()->after('approved_by');
            $table->string('decision_note')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropColumn(['decided_at', 'decision_note']);
        });
    }
};
