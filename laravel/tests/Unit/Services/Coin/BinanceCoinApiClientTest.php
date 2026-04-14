<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Coin;

use App\Services\Coin\BinanceCoinApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BinanceCoinApiClientTest extends TestCase
{
    public function test_fetch_top_coins_sorts_by_numeric_volume_and_prioritizes_large_caps(): void
    {
        Http::fake([
            'https://api.binance.com/api/v3/ticker/24hr' => Http::response([
                ['symbol' => 'DOGEUSDT', 'quoteVolume' => '100'],
                ['symbol' => 'BTCUSDT', 'quoteVolume' => '2'],
                ['symbol' => 'ETHUSDT', 'quoteVolume' => '11'],
                ['symbol' => 'XRPUSDT', 'quoteVolume' => '9'],
                ['symbol' => 'SOLUSDT', 'quoteVolume' => '8'],
                ['symbol' => 'ADAUSDT', 'quoteVolume' => '7'],
                ['symbol' => 'BNBUSDT', 'quoteVolume' => '6'],
                ['symbol' => 'TRXUSDT', 'quoteVolume' => '5'],
                ['symbol' => 'LINKUSDT', 'quoteVolume' => '4'],
                ['symbol' => 'AVAXUSDT', 'quoteVolume' => '3'],
                ['symbol' => 'PEPEUSDT', 'quoteVolume' => '999'],
                ['symbol' => 'USDCUSDT', 'quoteVolume' => '2000'],
                ['symbol' => 'BTCFDUSD', 'quoteVolume' => '5000'],
                ['symbol' => 'BTCUPUSDT', 'quoteVolume' => '3000'],
            ], 200),
        ]);

        $client = new BinanceCoinApiClient;
        $result = $client->fetchTopCoins();

        $this->assertCount(10, $result);
        $this->assertSame('BTCUSDT', $result[0]['symbol']);
        $this->assertSame('ETHUSDT', $result[1]['symbol']);
        $this->assertSame('BNBUSDT', $result[2]['symbol']);
        $this->assertSame('SOLUSDT', $result[3]['symbol']);
        $this->assertSame('XRPUSDT', $result[4]['symbol']);
        $this->assertSame('ADAUSDT', $result[5]['symbol']);
        $this->assertSame('DOGEUSDT', $result[6]['symbol']);
        $this->assertSame('TRXUSDT', $result[7]['symbol']);
        $this->assertSame('LINKUSDT', $result[8]['symbol']);
        $this->assertSame('AVAXUSDT', $result[9]['symbol']);
    }
}
