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
        if (Schema::hasTable('feed_keywords')) {
            // If the table already exists, we can skip creating it
            return;
        }

        Schema::create('feed_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->enum('type', ['coin', 'scam', 'hack', 'etf', 'other'])->default('coin');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_keywords');
    }
};
