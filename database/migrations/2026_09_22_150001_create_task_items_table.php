<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lines inside a task.
 *
 * A task is rarely one motion. "Ramadan campaign designs" is three posts, a
 * cover and a story, and a board that can only say *started* or *done* about
 * the whole thing tells management nothing on the third day. The lines are
 * where the real progress is, so they get their own rows.
 *
 * Deliberately thin: a title and a tick. No owner (the task has one), no date
 * (the task has one), no priority. Every column added here is a decision
 * somebody has to make before they can write down "post the story", and the
 * point of the list is that writing a line costs nothing.
 *
 * `completed_at` **is** the done flag — one column, not a boolean beside a
 * timestamp that can disagree with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();

            $table->string('title');
            // The order management wrote them in. Not an id, because lines get
            // inserted between other lines.
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // Who wrote the line. This is what separates "management asked for
            // this" from "I did this as well" — and it decides who may delete
            // it, since nobody should be able to drop a line they were given.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['task_id', 'position']);
            // "What is still open on this task" — asked by every checklist and
            // by the check-out dialog.
            $table->index(['task_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
    }
};
