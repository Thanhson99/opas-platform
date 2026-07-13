<?php

declare(strict_types=1);

namespace App\Services\Douyin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Call the internal Douyin worker service.
 */
class DouyinWorkerClient
{
    /**
     * Crawl Douyin search results for one keyword.
     *
     * @param  string  $keyword
     * @param  int  $limit
     * @return array<string, mixed>
     */
    public function crawl(string $keyword, int $limit): array
    {
        return $this->post('/api/douyin/crawl', [
            'keyword' => $keyword,
            'limit' => $limit,
        ]);
    }

    /**
     * Download one Douyin video URL through the worker.
     *
     * @param  string  $url
     * @return array<string, mixed>
     */
    public function download(string $url): array
    {
        return $this->post('/api/douyin/download', [
            'url' => $url,
            'quality' => 'best',
            'networkWaitMs' => 12000,
        ]);
    }

    /**
     * Crawl and download in one worker request.
     *
     * @param  string  $keyword
     * @param  int  $limit
     * @return array<string, mixed>
     */
    public function crawlAndDownload(string $keyword, int $limit): array
    {
        return $this->post('/api/douyin/crawl-and-download', [
            'keyword' => $keyword,
            'limit' => $limit,
            'download' => true,
            'quality' => 'best',
            'networkWaitMs' => 12000,
        ]);
    }

    /**
     * Create the configured HTTP client.
     *
     * @return PendingRequest
     */
    private function client(): PendingRequest
    {
        $configuredBaseUrl = config('services.douyin_worker.url');
        $configuredApiKey = config('services.douyin_worker.api_key');
        $baseUrl = is_string($configuredBaseUrl) ? $configuredBaseUrl : 'http://localhost:3101';
        $apiKey = is_string($configuredApiKey) ? $configuredApiKey : '';

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withHeaders(['x-api-key' => $apiKey])
            ->acceptJson()
            ->asJson()
            ->timeout(300)
            ->retry(2, 1000);
    }

    /**
     * Send one POST request and unwrap worker data.
     *
     * @param  string  $path
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->client()->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Douyin worker service is not available.', 0, $exception);
        }

        if ($response->failed()) {
            $message = $response->json('message');

            throw new RuntimeException(
                is_scalar($message) && (string) $message !== ''
                    ? (string) $message
                    : 'Douyin worker service returned an error.'
            );
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Douyin worker returned an invalid response.');
        }

        /** @var array<string, mixed> $typedData */
        $typedData = $data;

        return $typedData;
    }
}
