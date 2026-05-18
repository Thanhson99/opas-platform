<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_auth_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key');
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->json('metadata')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_key', 'provider_user_id']);
            $table->index(['user_id', 'provider_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_auth_identities');
    }
};
