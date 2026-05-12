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
        if (Schema::hasTable('feed_keyword_tag')) {
            // If the table already exists, we can skip creating it
            return;
        }

        Schema::create('feed_keyword_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('feed_keyword_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->primary(['feed_keyword_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_keyword_tag');
    }
};
