<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class FeedKeyword
 *
 * @property int $id
 * @property string $keyword
 * @property string|null $type
 * @property bool $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FeedKeyword extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'feed_keywords';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'keyword',
        'type',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the tags associated with the feed keyword.
     *
     * @return BelongsToMany<Tag>
     *
     * @phpstan-return BelongsToMany<
     *     \App\Models\Tag,
     *     \App\Models\FeedKeyword
     * >
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'feed_keyword_tag',
            'feed_keyword_id',
            'tag_id'
        );
    }
}
