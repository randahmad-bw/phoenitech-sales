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
        Schema::create('server_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('type')->default('hosting'); // vps, hosting, domain, email, ssl
            $table->string('domain')->nullable();
            $table->string('provider')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('start_date')->nullable();
            $table->date('end_date');
            $table->string('status', 30)->default('active'); // active, expiring_soon, expired, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for fast querying
            $table->index('type');
            $table->index('status');
            $table->index('end_date');
            $table->index('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_subscriptions');
    }
};
