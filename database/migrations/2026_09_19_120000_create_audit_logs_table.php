<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail: who did what, when, and what changed.
 *
 * Rows are append-only — there is no updated_at because an audit entry is never
 * edited. The actor's name/email are snapshotted alongside user_id so the trail
 * stays readable after the account is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor. Null for system/console actions or a failed login by an
            // identifier that matches no account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            // What happened — see the AuditLog::EVENT_* constants:
            // created | updated | deleted | login | login_failed | login_blocked |
            // logout | password_changed | password_reset | roles_changed |
            // permissions_changed.
            $table->string('event', 40);

            // The affected record. Null for auth events that target no model.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            // Human-readable snapshot (contract number, company name, …) so the
            // trail still names the record after it is deleted.
            $table->string('auditable_label')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
