<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Seed the stocks table.
     */
    public function run(): void
    {
        $stockData = require database_path('seeders/data/vn_stocks.php');

        if (! is_array($stockData) || $stockData === []) {
            $this->command?->warn('No stock data found to seed.');

            return;
        }

        $now = now();

        $payload = array_map(
            static fn (array $stock): array => [
                'symbol' => $stock['symbol'],
                'name' => $stock['name'],
                'exchange' => $stock['exchange'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $stockData
        );

        Stock::query()->upsert(
            $payload,
            ['symbol'],
            ['name', 'exchange', 'updated_at']
        );
    }
}
