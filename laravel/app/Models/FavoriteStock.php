<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FavoriteStock
 *
 * Represents a user's favorite stock symbol.
 */
class FavoriteStock extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'symbol',
    ];
}
