<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a feed keywords
        $this->call(FeedKeywordSeeder::class);
        $this->call(CoinAlertSettingsSeeder::class);
        $this->call(StockSeeder::class);
    }
}
