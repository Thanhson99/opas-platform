<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('douyin_keywords', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('source')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('douyin_keywords');
    }
};
