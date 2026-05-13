<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the coin index page loads successfully.
     */
    public function test_it_can_display_coin_index_page(): void
    {
        $response = $this->get(route('coins.index'));

        $response->assertStatus(200);
        $response->assertViewIs('spa');
    }

    /**
     * Test the coin detail page loads successfully.
     */
    public function test_it_can_display_coin_detail_page(): void
    {
        $response = $this->get(route('coins.show', 'BTCUSDT'));

        $response->assertStatus(200);
        $response->assertViewIs('spa');
    }
}
