<?php

namespace App\Services\Python;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PythonService handles calling specific Python microservice endpoints.
 */
class PythonService
{
    protected string $baseUrl;

    public function __construct()
    {
        // Ensure base URL is always a string
        $base = config('services.python.base_url');
        $this->baseUrl = is_string($base) ? $base : '';
    }

    /**
     * Call Douyin downloader endpoint.
     *
     * @return array<string, mixed>
     */
    public function downloadVideo(): array
    {
        $path = config('services.python.douyin_path');
        $url = $this->baseUrl.(is_string($path) ? $path : '');

        return $this->call($url);
    }

    /**
     * Call AI Caption generator endpoint.
     *
     * @return array<string, mixed>
     */
    public function generateCaption(): array
    {
        $path = config('services.python.caption_path');
        $url = $this->baseUrl.(is_string($path) ? $path : '');

        return $this->call($url);
    }

    /**
     * Call Trending keywords endpoint.
     *
     * @return array<string, mixed>
     */
    public function trendingKeywords(): array
    {
        $path = config('services.python.trending_path');
        $url = $this->baseUrl.(is_string($path) ? $path : '');

        return $this->call($url);
    }

    /**
     * Generic HTTP GET call to Python service.
     *
     * @return array<string, mixed>
     */
    protected function call(string $url): array
    {
        /** @var Response $response */
        $response = Http::get($url);

        if ($response->failed()) {
            // Some failures may return HTML/text; avoid assuming JSON.
            $body = $response->body();
            Log::error("Python service call failed: {$url}", [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 2000),
            ]);

            return [];
        }

        $data = $response->json();

        if (! is_array($data) || empty($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return array_combine(
            array_map('strval', array_keys($data)),
            array_values($data)
        );
    }
}
