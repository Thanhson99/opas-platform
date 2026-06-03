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
        Schema::create('telegram_bot_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('display_name');
            $table->string('purpose', 50)->default('remote_control');
            $table->string('environment', 50)->default('local');
            $table->string('machine_group', 100)->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('locale', 10)->default('en');
            $table->string('api_base_url')->nullable();
            $table->json('allowed_chat_ids')->nullable();
            $table->json('allowed_user_ids')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->json('public_config')->nullable();
            $table->longText('secret_config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_configs');
    }
};
