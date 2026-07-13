<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persist reusable Douyin keyword inputs.
 *
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string|null $source
 * @property int $priority
 * @property bool $is_active
 */
class DouyinKeyword extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'source',
        'priority',
        'is_active',
        'last_crawled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_crawled_at' => 'datetime',
    ];

    /**
     * Get crawl jobs created from this keyword.
     *
     * @return HasMany<DouyinCrawlJob, $this>
     *
     * @phpstan-return HasMany<DouyinCrawlJob, $this>
     */
    public function crawlJobs(): HasMany
    {
        return $this->hasMany(DouyinCrawlJob::class, 'keyword_id');
    }
}
