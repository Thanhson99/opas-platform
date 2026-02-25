<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Stock
 *
 * Represents a listed stock on Vietnam exchanges.
 */
class Stock extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'symbol',
        'name',
        'exchange',
    ];
}
