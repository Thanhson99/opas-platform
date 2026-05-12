<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoinAlertSettingsSeeder extends Seeder
{
    /**
     * Seed the coin_alert_settings table from JSON file.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/coin_alert_settings.json');

        // Ensure data file exists
        if (! file_exists($path)) {
            $this->command->error("Data file not found: {$path}");

            return;
        }

        // Decode JSON
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            $this->command->error("Invalid JSON format in: {$path}");

            return;
        }

        // Optional: fill missing fields to prevent DB errors
        foreach ($data as &$row) {
            $row['is_active'] = $row['is_active'] ?? true;
            $row['type'] = $row['type'] ?? 'preset';
        }

        // Clear old data
        DB::table('coin_alert_settings')->truncate();

        // Add timestamps
        $now = now();
        foreach ($data as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        // Insert into database
        DB::table('coin_alert_settings')->insert($data);

        $this->command->info('Seeded coin_alert_settings from JSON successfully.');
    }
}
