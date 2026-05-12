<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Tag
 *
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tag extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get all feed keywords associated with this tag.
     *
     * @return BelongsToMany<FeedKeyword>
     *
     * @phpstan-return BelongsToMany<
     *     FeedKeyword,
     *     Tag
     * >
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(
            FeedKeyword::class,
            'feed_keyword_tag',
            'tag_id',
            'feed_keyword_id'
        );
    }
}
