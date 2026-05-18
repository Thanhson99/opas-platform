<?php

declare(strict_types=1);

use App\Enums\AuthProviderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('display_name');
            $table->string('type')->default(AuthProviderType::Password->value);
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(100);
            $table->string('icon')->nullable();
            $table->json('capabilities');
            $table->json('public_config')->nullable();
            $table->text('secret_config')->nullable();
            $table->string('email_verification_mode')->nullable();
            $table->timestamps();
        });

        DB::table('auth_providers')->insert([
            [
                'key' => 'email',
                'display_name' => 'Email and Password',
                'type' => AuthProviderType::Password->value,
                'enabled' => true,
                'sort_order' => 10,
                'icon' => 'mail',
                'capabilities' => json_encode([
                    'login' => true,
                    'register' => true,
                    'link_account' => false,
                    'requires_redirect' => false,
                    'supports_email_verification' => true,
                    'supports_password' => true,
                ], JSON_THROW_ON_ERROR),
                'public_config' => json_encode([], JSON_THROW_ON_ERROR),
                'secret_config' => encrypt(json_encode([], JSON_THROW_ON_ERROR)),
                'email_verification_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'google',
                'display_name' => 'Google',
                'type' => AuthProviderType::OAuth2->value,
                'enabled' => false,
                'sort_order' => 20,
                'icon' => 'google',
                'capabilities' => json_encode([
                    'login' => true,
                    'register' => true,
                    'link_account' => true,
                    'requires_redirect' => true,
                    'supports_email_verification' => true,
                    'supports_password' => false,
                ], JSON_THROW_ON_ERROR),
                'public_config' => json_encode([], JSON_THROW_ON_ERROR),
                'secret_config' => encrypt(json_encode([], JSON_THROW_ON_ERROR)),
                'email_verification_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'facebook',
                'display_name' => 'Facebook',
                'type' => AuthProviderType::OAuth2->value,
                'enabled' => false,
                'sort_order' => 30,
                'icon' => 'facebook',
                'capabilities' => json_encode([
                    'login' => true,
                    'register' => true,
                    'link_account' => true,
                    'requires_redirect' => true,
                    'supports_email_verification' => true,
                    'supports_password' => false,
                ], JSON_THROW_ON_ERROR),
                'public_config' => json_encode([], JSON_THROW_ON_ERROR),
                'secret_config' => encrypt(json_encode([], JSON_THROW_ON_ERROR)),
                'email_verification_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'github',
                'display_name' => 'GitHub',
                'type' => AuthProviderType::OAuth2->value,
                'enabled' => false,
                'sort_order' => 40,
                'icon' => 'github',
                'capabilities' => json_encode([
                    'login' => true,
                    'register' => true,
                    'link_account' => true,
                    'requires_redirect' => true,
                    'supports_email_verification' => true,
                    'supports_password' => false,
                ], JSON_THROW_ON_ERROR),
                'public_config' => json_encode([], JSON_THROW_ON_ERROR),
                'secret_config' => encrypt(json_encode([], JSON_THROW_ON_ERROR)),
                'email_verification_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_providers');
    }
};
