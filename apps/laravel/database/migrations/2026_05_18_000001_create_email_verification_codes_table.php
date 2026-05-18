<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the short-lived email verification code storage table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Drop the email verification code storage table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
