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
        Schema::create('douyin_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crawl_job_id')->nullable()->constrained('douyin_crawl_jobs')->nullOnDelete();
            $table->string('keyword')->nullable()->index();
            $table->string('video_id')->unique();
            $table->text('source_url');
            $table->text('title')->nullable();
            $table->string('author')->nullable();
            $table->text('cover_url')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('like_count')->nullable();
            $table->text('local_path')->nullable();
            $table->text('metadata_path')->nullable();
            $table->boolean('selected')->default(true)->index();
            $table->string('status')->default('preview')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('douyin_videos');
    }
};
