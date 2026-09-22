<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The follow-up thread on a task.
 *
 * Kept as its own table rather than a `notes` column so that "where did this
 * get stuck" has an answer with a name and a time on it. Comments are
 * append-only from the UI: there is no edit endpoint, because a thread people
 * can rewrite is not a record of anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Kept so a comment still says who wrote it after the account goes.
            $table->string('author_name')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
