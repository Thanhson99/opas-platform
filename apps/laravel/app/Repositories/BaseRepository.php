<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * Base repository class for Eloquent models.
 */
abstract class BaseRepository
{
    /**
     * The Eloquent model instance.
     *
     * @var TModel
     */
    protected Model $model;

    /**
     * BaseRepository constructor.
     *
     * @param  TModel  $model
     * @return void
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all models.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get model instance.
     *
     * @return TModel
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
