<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the persisted workflow-step table for auto-coding runs.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('auto_coding_task_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_run_id')->constrained('auto_coding_task_runs')->cascadeOnDelete();
            $table->string('step_key')->index();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('attempt')->default(1);
            $table->string('status')->index();
            $table->boolean('is_retryable')->default(false);
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop the persisted workflow-step table for auto-coding runs.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_coding_task_run_steps');
    }
};
