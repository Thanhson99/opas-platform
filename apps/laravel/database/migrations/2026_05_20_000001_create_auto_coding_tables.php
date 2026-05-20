<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the local auto-coding persistence tables.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('auto_coding_machines', function (Blueprint $table): void {
            $table->id();
            $table->string('machine_key')->unique();
            $table->string('hostname');
            $table->string('operating_system');
            $table->string('repository_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auto_coding_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('summary');
            $table->string('issue_key')->nullable()->index();
            $table->string('repository_path');
            $table->string('branch_name')->nullable();
            $table->string('status')->index();
            $table->json('context_payload')->nullable();
            $table->json('latest_report')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auto_coding_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('auto_coding_tasks')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('auto_coding_machines')->cascadeOnDelete();
            $table->string('status')->index();
            $table->json('repository_snapshot');
            $table->json('changed_files')->nullable();
            $table->json('provider_result')->nullable();
            $table->json('validation_results')->nullable();
            $table->json('final_report')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auto_coding_run_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_run_id')->constrained('auto_coding_task_runs')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('label');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop the local auto-coding persistence tables.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_coding_run_artifacts');
        Schema::dropIfExists('auto_coding_task_runs');
        Schema::dropIfExists('auto_coding_tasks');
        Schema::dropIfExists('auto_coding_machines');
    }
};
