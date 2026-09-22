<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work assigned to one person, with a due date and a state.
 *
 * A task always has exactly one owner. Shared ownership reads well on a board
 * and fails in practice — when two people own a task nobody does — so the
 * column is a plain `assigned_to`, not a pivot table.
 *
 * `started_at` / `completed_at` are written by the service when the status
 * moves, never by the client: they are what the "how long did this take"
 * question is answered from, and a client-supplied timestamp answers nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // The one person responsible. Deleting an employee does not delete
            // the history of what they were asked to do.
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            // Who asked for it — an account, because managers assign, not employees.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Optional context: the client this work is for.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->enum('status', ['todo', 'in_progress', 'done', 'cancelled'])->default('todo');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();

            $table->timestamps();

            // The two questions the screens actually ask: "what is on this
            // person's plate" and "what is open and overdue".
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
