<?php

namespace Tests\Feature;

use Tests\TestCase;

class VideoAutomationControllerTest extends TestCase
{
    public function test_it_can_display_trending_video_page(): void
    {
        $response = $this->get(route('video-automation.trending.index'));

        $response->assertStatus(200);
        $response->assertViewIs('spa');
    }
}
