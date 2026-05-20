<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add machine access-token fields for agent-facing auto-coding authentication.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('auto_coding_machines', function (Blueprint $table): void {
            $table->string('access_token_hash')->nullable()->after('repository_path');
            $table->timestamp('access_token_last_used_at')->nullable()->after('access_token_hash');
        });
    }

    /**
     * Drop machine access-token fields for agent-facing auto-coding authentication.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('auto_coding_machines', function (Blueprint $table): void {
            $table->dropColumn([
                'access_token_hash',
                'access_token_last_used_at',
            ]);
        });
    }
};
