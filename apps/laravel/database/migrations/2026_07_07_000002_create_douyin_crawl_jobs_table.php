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
        Schema::create('douyin_crawl_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('keyword_id')->nullable()->constrained('douyin_keywords')->nullOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('limit')->default(20);
            $table->string('status');
            $table->unsignedInteger('total_found')->default(0);
            $table->unsignedInteger('total_selected')->default(0);
            $table->unsignedInteger('total_downloaded')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('keyword');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('douyin_crawl_jobs');
    }
};
