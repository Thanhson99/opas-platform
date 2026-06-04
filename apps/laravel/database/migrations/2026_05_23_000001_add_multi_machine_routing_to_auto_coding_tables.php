<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add multi-machine availability, workspace binding, and task routing fields.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('auto_coding_machines', function (Blueprint $table): void {
            $table->string('availability_status')->default('idle')->after('operating_system')->index();
            $table->json('capabilities')->nullable()->after('repository_path');
            $table->json('workspace_bindings')->nullable()->after('capabilities');
            $table->unsignedSmallInteger('max_parallel_tasks')->default(1)->after('workspace_bindings');
        });

        Schema::table('auto_coding_tasks', function (Blueprint $table): void {
            $table->foreignId('assigned_machine_id')
                ->nullable()
                ->after('branch_name')
                ->constrained('auto_coding_machines')
                ->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('assigned_machine_id');
        });
    }

    /**
     * Drop multi-machine routing fields.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('auto_coding_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_machine_id');
            $table->dropColumn('claimed_at');
        });

        Schema::table('auto_coding_machines', function (Blueprint $table): void {
            $table->dropColumn([
                'availability_status',
                'capabilities',
                'workspace_bindings',
                'max_parallel_tasks',
            ]);
        });
    }
};
