<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default admin account for local development.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@opas.local'],
            [
                'name' => 'OPAS Admin',
                'password' => 'Admin123!',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info('Default admin account seeded: admin@opas.local');
    }
}
