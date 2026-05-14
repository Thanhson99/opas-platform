<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the stock index page loads successfully.
     */
    public function test_it_can_display_stock_index_page(): void
    {
        $response = $this->get(route('stocks.index'));

        $response->assertStatus(200);
        $response->assertViewIs('spa');
    }
}
