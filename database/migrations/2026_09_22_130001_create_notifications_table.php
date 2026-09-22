<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The in-app notification feed.
 *
 * Nothing is stored as a finished sentence. A notification keeps its `type`
 * and the few facts behind it (`data`), and the screen renders the sentence in
 * whichever language the reader is using — an Arabic string written here would
 * still be Arabic after the user switches to English, forever.
 *
 * `read_at` rather than a boolean: knowing *when* somebody saw a thing is the
 * question that actually gets asked, and "is it read" is derivable from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            // The facts the sentence is built from: task title, who acted, etc.
            $table->json('data')->nullable();
            // Where the notification takes you when clicked (an SPA path).
            $table->string('link')->nullable();
            // What it is about, so a deleted task takes its notices with it.
            $table->nullableMorphs('subject');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The two questions the feed asks: "my unread" and "my latest".
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
