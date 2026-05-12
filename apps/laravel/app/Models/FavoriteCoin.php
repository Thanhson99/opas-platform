<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FavoriteCoin
 *
 * Represents a user's favorite cryptocurrency symbol.
 */
class FavoriteCoin extends Model
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
