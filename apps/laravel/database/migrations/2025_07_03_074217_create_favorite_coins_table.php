<?php

declare(strict_types=1);

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
        if (Schema::hasTable('favorite_coins')) {
            // If the table already exists, we can skip creating it
            return;
        }

        Schema::create('favorite_coins', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->index();
            $table->decimal('last_price', 18, 8)
                ->nullable()
                ->comment('Most recent price of the coin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_coins');
    }
};
