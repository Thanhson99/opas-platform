<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Track one Douyin crawl-preview workflow.
 *
 * @property int $id
 * @property int|null $keyword_id
 * @property string $keyword
 * @property int $limit
 * @property string $status
 */
class DouyinCrawlJob extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'keyword_id',
        'keyword',
        'limit',
        'status',
        'total_found',
        'total_selected',
        'total_downloaded',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get the keyword record attached to this job.
     *
     * @return BelongsTo<DouyinKeyword, $this>
     *
     * @phpstan-return BelongsTo<DouyinKeyword, $this>
     */
    public function keywordRecord(): BelongsTo
    {
        return $this->belongsTo(DouyinKeyword::class, 'keyword_id');
    }

    /**
     * Get videos discovered by this job.
     *
     * @return HasMany<DouyinVideo, $this>
     *
     * @phpstan-return HasMany<DouyinVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(DouyinVideo::class, 'crawl_job_id');
    }
}
