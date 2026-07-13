<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist one Douyin video through preview and download states.
 *
 * @property int $id
 * @property string $video_id
 * @property string $source_url
 * @property string $status
 * @property bool $selected
 */
class DouyinVideo extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'crawl_job_id',
        'keyword',
        'video_id',
        'source_url',
        'title',
        'author',
        'cover_url',
        'duration',
        'like_count',
        'local_path',
        'metadata_path',
        'selected',
        'status',
        'error_message',
        'downloaded_at',
        'processed_at',
        'posted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'selected' => 'boolean',
        'downloaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    /**
     * Get the crawl job that discovered this video.
     *
     * @return BelongsTo<DouyinCrawlJob, $this>
     *
     * @phpstan-return BelongsTo<DouyinCrawlJob, $this>
     */
    public function crawlJob(): BelongsTo
    {
        return $this->belongsTo(DouyinCrawlJob::class, 'crawl_job_id');
    }
}
