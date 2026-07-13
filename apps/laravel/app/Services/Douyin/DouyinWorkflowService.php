<?php

declare(strict_types=1);

namespace App\Services\Douyin;

use App\Models\DouyinCrawlJob;
use App\Models\DouyinKeyword;
use App\Models\DouyinVideo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Coordinate Douyin preview, selection, and download workflows.
 */
class DouyinWorkflowService
{
    public function __construct(
        private readonly DouyinWorkerClient $workerClient,
    ) {}

    /**
     * Return active Douyin keyword presets.
     *
     * @return Collection<int, DouyinKeyword>
     */
    public function keywords(): Collection
    {
        return DouyinKeyword::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();
    }

    /**
     * Store a user-created Douyin keyword.
     *
     * @param  array{name: string, category?: string|null}  $payload
     * @return DouyinKeyword
     */
    public function createKeyword(array $payload): DouyinKeyword
    {
        return DouyinKeyword::query()->updateOrCreate(
            ['name' => $payload['name']],
            [
                'category' => $payload['category'] ?? null,
                'source' => 'manual',
                'is_active' => true,
            ]
        );
    }

    /**
     * Crawl preview cards and persist discovered videos.
     *
     * @param  string  $keyword
     * @param  int  $limit
     * @return DouyinCrawlJob
     */
    public function crawlPreview(string $keyword, int $limit): DouyinCrawlJob
    {
        $keywordRecord = $this->resolveKeyword($keyword);
        $job = $this->createCrawlingJob($keywordRecord, $keyword, $limit);

        try {
            $result = $this->workerClient->crawl($keyword, $limit);
            $videos = $this->storePreviewVideos($job, $keyword, $this->normalizeItems($result['items'] ?? []));

            $job->forceFill([
                'status' => 'preview_ready',
                'total_found' => $videos,
                'total_selected' => $videos,
                'finished_at' => now(),
            ])->save();

            $keywordRecord?->forceFill(['last_crawled_at' => now()])->save();

            return $job->load('videos');
        } catch (Throwable $throwable) {
            $job->forceFill([
                'status' => $this->isManualRequired($throwable) ? 'failed' : 'failed',
                'error_message' => $throwable->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $throwable;
        }
    }

    /**
     * Return recent crawl jobs.
     *
     * @return Collection<int, DouyinCrawlJob>
     */
    public function jobs(): Collection
    {
        return DouyinCrawlJob::query()
            ->withCount('videos')
            ->latest()
            ->limit(30)
            ->get();
    }

    /**
     * Update whether a video should be processed.
     *
     * @param  DouyinVideo  $video
     * @param  bool  $selected
     * @return DouyinVideo
     */
    public function updateSelection(DouyinVideo $video, bool $selected): DouyinVideo
    {
        $video->forceFill([
            'selected' => $selected,
            'status' => $selected ? 'selected' : 'rejected',
        ])->save();

        $this->refreshJobTotals($video->crawlJob);

        return $video->refresh();
    }

    /**
     * Download selected videos for one job synchronously.
     *
     * @param  DouyinCrawlJob  $job
     * @return Collection<int, DouyinVideo>
     */
    public function processSelected(DouyinCrawlJob $job): Collection
    {
        $job->forceFill(['status' => 'processing'])->save();

        /** @var Collection<int, DouyinVideo> $videos */
        $videos = $job->videos()
            ->where('selected', true)
            ->whereIn('status', ['preview', 'selected'])
            ->get();

        foreach ($videos as $video) {
            $this->downloadVideo($video);
        }

        $this->refreshJobTotals($job);
        $job->forceFill([
            'status' => 'completed',
            'finished_at' => now(),
        ])->save();

        return $job->videos()->latest()->get();
    }

    /**
     * Paginate videos by optional filters.
     *
     * @param  array{status?: string|null, keyword?: string|null, page?: int|null}  $filters
     * @return LengthAwarePaginator<int, DouyinVideo>
     */
    public function videos(array $filters): LengthAwarePaginator
    {
        return DouyinVideo::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['keyword'] ?? null, fn ($query, string $keyword) => $query->where('keyword', $keyword))
            ->latest()
            ->paginate(24);
    }

    /**
     * Mark a video posted and optionally delete local files.
     *
     * @param  DouyinVideo  $video
     * @param  bool  $deleteAfterPosted
     * @return DouyinVideo
     */
    public function markPosted(DouyinVideo $video, bool $deleteAfterPosted): DouyinVideo
    {
        if ($deleteAfterPosted) {
            $this->deleteVideoFiles($video);
        }

        $video->forceFill([
            'status' => 'posted',
            'posted_at' => now(),
            'local_path' => $deleteAfterPosted ? null : $video->local_path,
            'metadata_path' => $deleteAfterPosted ? null : $video->metadata_path,
        ])->save();

        return $video->refresh();
    }

    /**
     * Delete a video row and any local files.
     *
     * @param  DouyinVideo  $video
     * @return void
     */
    public function deleteVideo(DouyinVideo $video): void
    {
        $job = $video->crawlJob;

        $this->deleteVideoFiles($video);
        $video->delete();
        $this->refreshJobTotals($job);
    }

    /**
     * Create a job in crawling status.
     *
     * @param  DouyinKeyword|null  $keywordRecord
     * @param  string  $keyword
     * @param  int  $limit
     * @return DouyinCrawlJob
     */
    private function createCrawlingJob(?DouyinKeyword $keywordRecord, string $keyword, int $limit): DouyinCrawlJob
    {
        return DouyinCrawlJob::query()->create([
            'keyword_id' => $keywordRecord?->id,
            'keyword' => $keyword,
            'limit' => $limit,
            'status' => 'crawling',
            'started_at' => now(),
        ]);
    }

    /**
     * Resolve an active keyword record when one exists.
     *
     * @param  string  $keyword
     * @return DouyinKeyword|null
     */
    private function resolveKeyword(string $keyword): ?DouyinKeyword
    {
        return DouyinKeyword::query()->where('name', $keyword)->first();
    }

    /**
     * Normalize worker items to arrays.
     *
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalizedItems = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $typedItem */
                $typedItem = $item;
                $normalizedItems[] = $typedItem;
            }
        }

        return $normalizedItems;
    }

    /**
     * Store preview video rows from worker cards.
     *
     * @param  DouyinCrawlJob  $job
     * @param  string  $keyword
     * @param  list<array<string, mixed>>  $items
     * @return int
     */
    private function storePreviewVideos(DouyinCrawlJob $job, string $keyword, array $items): int
    {
        $stored = 0;

        foreach ($items as $item) {
            $videoId = $this->resolveVideoId($item);

            if ($videoId === null) {
                continue;
            }

            DouyinVideo::query()->updateOrCreate(
                ['video_id' => $videoId],
                [
                    'crawl_job_id' => $job->id,
                    'keyword' => $keyword,
                    'source_url' => $this->resolveSourceUrl($item, $videoId),
                    'title' => $this->nullableString($item['title'] ?? null),
                    'author' => $this->nullableString($item['author'] ?? null),
                    'selected' => true,
                    'status' => 'preview',
                    'error_message' => null,
                ]
            );
            $stored++;
        }

        return $stored;
    }

    /**
     * Resolve a video ID from worker payload fields.
     *
     * @param  array<string, mixed>  $item
     * @return string|null
     */
    private function resolveVideoId(array $item): ?string
    {
        if (is_scalar($item['videoId'] ?? null) && (string) $item['videoId'] !== '') {
            return (string) $item['videoId'];
        }

        $url = is_scalar($item['url'] ?? null) ? (string) $item['url'] : '';

        if (preg_match('#/video/(\d+)#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Download one video and persist the worker result.
     *
     * @param  DouyinVideo  $video
     * @return void
     */
    private function downloadVideo(DouyinVideo $video): void
    {
        $video->forceFill(['status' => 'downloading', 'error_message' => null])->save();

        try {
            $result = $this->workerClient->download($video->source_url);
            $status = is_scalar($result['status'] ?? null) ? (string) $result['status'] : 'failed';

            $video->forceFill([
                'status' => in_array($status, ['downloaded', 'skipped'], true) ? 'downloaded' : 'failed',
                'local_path' => $this->nullableString($result['localFile'] ?? null),
                'metadata_path' => $this->nullableString($result['metadataFile'] ?? null),
                'error_message' => $this->nullableString($result['error'] ?? null),
                'downloaded_at' => in_array($status, ['downloaded', 'skipped'], true) ? now() : null,
            ])->save();
        } catch (Throwable $throwable) {
            $video->forceFill([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }

    /**
     * Resolve the source URL from worker payload or video ID.
     *
     * @param  array<string, mixed>  $item
     * @param  string  $videoId
     * @return string
     */
    private function resolveSourceUrl(array $item, string $videoId): string
    {
        if (is_scalar($item['url'] ?? null) && (string) $item['url'] !== '') {
            return (string) $item['url'];
        }

        return "https://www.douyin.com/video/{$videoId}";
    }

    /**
     * Delete local paths attached to a video.
     *
     * @param  DouyinVideo  $video
     * @return void
     */
    private function deleteVideoFiles(DouyinVideo $video): void
    {
        foreach ([$video->local_path, $video->metadata_path] as $path) {
            if (is_string($path) && $path !== '' && File::exists($path)) {
                File::delete($path);
            }
        }
    }

    /**
     * Refresh aggregate totals for a crawl job.
     *
     * @param  DouyinCrawlJob|null  $job
     * @return void
     */
    private function refreshJobTotals(?DouyinCrawlJob $job): void
    {
        if (! $job instanceof DouyinCrawlJob) {
            return;
        }

        $job->forceFill([
            'total_found' => $job->videos()->count(),
            'total_selected' => $job->videos()->where('selected', true)->count(),
            'total_downloaded' => $job->videos()->where('status', 'downloaded')->count(),
        ])->save();
    }

    /**
     * Convert mixed scalar values to nullable strings.
     *
     * @param  mixed  $value
     * @return string|null
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * Determine if a worker error indicates manual browser action.
     *
     * @param  Throwable  $throwable
     * @return bool
     */
    private function isManualRequired(Throwable $throwable): bool
    {
        return str_contains($throwable->getMessage(), 'MANUAL_REQUIRED');
    }
}
