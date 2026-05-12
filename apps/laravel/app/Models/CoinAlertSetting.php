<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoinAlertSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CoinAlertSetting
 *
 * Represents a threshold configuration for coin alerts.
 *
 * @property int $id
 * @property string $mode
 * @property float|null $threshold_percent
 * @property string|null $type
 * @property string|null $direction
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @mixin \Eloquent
 *
 * @method static CoinAlertSettingFactory factory(...$parameters)
 */
class CoinAlertSetting extends Model
{
    /** @use HasFactory<CoinAlertSettingFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'coin_alert_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mode',
        'threshold_percent',
        'type',
        'direction',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'threshold_percent' => 'float',
        'is_active' => 'boolean',
    ];
}
