<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding\Interfaces;

use App\Models\TelegramBotConfig;
use Illuminate\Database\Eloquent\Collection;

interface TelegramBotConfigRepositoryInterface
{
    /**
     * @return Collection<int, TelegramBotConfig>
     */
    public function getOrdered(): Collection;

    /**
     * @return TelegramBotConfig|null
     */
    public function findByKey(string $key): ?TelegramBotConfig;

    /**
     * @return TelegramBotConfig|null
     */
    public function findDefault(): ?TelegramBotConfig;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TelegramBotConfig
     */
    public function create(array $attributes): TelegramBotConfig;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TelegramBotConfig
     */
    public function update(TelegramBotConfig $config, array $attributes): TelegramBotConfig;

    /**
     * @return void
     */
    public function delete(TelegramBotConfig $config): void;

    /**
     * @param  array<string, mixed>  $defaults
     * @return TelegramBotConfig
     */
    public function firstOrCreateByKey(string $key, array $defaults): TelegramBotConfig;

    /**
     * @return bool
     */
    public function hasAnotherDefault(int $excludedId): bool;

    /**
     * @return void
     */
    public function clearDefaultFlagExcept(?int $excludedId): void;
}
