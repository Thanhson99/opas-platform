<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoinAlertSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoinAlertSetting>
 */
class CoinAlertSettingFactory extends Factory
{
    protected $model = CoinAlertSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mode' => $this->faker->word(),
            'threshold_percent' => $this->faker->randomFloat(2, 0, 100),
            'type' => $this->faker->randomElement(['preset', 'custom']),
            'direction' => $this->faker->randomElement(['increase', 'decrease']),
            'is_active' => $this->faker->boolean(),
        ];
    }
}
