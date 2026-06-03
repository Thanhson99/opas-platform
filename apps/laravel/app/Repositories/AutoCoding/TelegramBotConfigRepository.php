<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Models\TelegramBotConfig;
use App\Repositories\AutoCoding\Interfaces\TelegramBotConfigRepositoryInterface;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TelegramBotConfig>
 */
class TelegramBotConfigRepository extends BaseRepository implements TelegramBotConfigRepositoryInterface
{
    /**
     * @return void
     */
    public function __construct(TelegramBotConfig $model)
    {
        parent::__construct($model);
    }

    /**
     * @return Collection<int, TelegramBotConfig>
     */
    public function getOrdered(): Collection
    {
        return $this->model
            ->newQuery()
            ->orderByDesc('is_default')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @return TelegramBotConfig|null
     */
    public function findByKey(string $key): ?TelegramBotConfig
    {
        $config = $this->model
            ->newQuery()
            ->where('key', $key)
            ->first();

        return $config instanceof TelegramBotConfig ? $config : null;
    }

    /**
     * @return TelegramBotConfig|null
     */
    public function findDefault(): ?TelegramBotConfig
    {
        $config = $this->model
            ->newQuery()
            ->where('is_default', true)
            ->orderByDesc('enabled')
            ->orderBy('id')
            ->first();

        return $config instanceof TelegramBotConfig ? $config : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TelegramBotConfig
     */
    public function create(array $attributes): TelegramBotConfig
    {
        /** @var TelegramBotConfig $config */
        $config = $this->model
            ->newQuery()
            ->create($attributes);

        return $config->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TelegramBotConfig
     */
    public function update(TelegramBotConfig $config, array $attributes): TelegramBotConfig
    {
        $config->fill($attributes);
        $config->save();

        return $config->refresh();
    }

    /**
     * @return void
     */
    public function delete(TelegramBotConfig $config): void
    {
        $config->delete();
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return TelegramBotConfig
     */
    public function firstOrCreateByKey(string $key, array $defaults): TelegramBotConfig
    {
        /** @var TelegramBotConfig $config */
        $config = $this->model
            ->newQuery()
            ->firstOrCreate(['key' => $key], $defaults);

        return $config;
    }

    /**
     * @return bool
     */
    public function hasAnotherDefault(int $excludedId): bool
    {
        return $this->model
            ->newQuery()
            ->where('is_default', true)
            ->where('id', '!=', $excludedId)
            ->exists();
    }

    /**
     * @return void
     */
    public function clearDefaultFlagExcept(?int $excludedId): void
    {
        $query = $this->model
            ->newQuery()
            ->where('is_default', true);

        if (is_int($excludedId) && $excludedId > 0) {
            $query->where('id', '!=', $excludedId);
        }

        $query->update([
            'is_default' => false,
        ]);
    }
}
