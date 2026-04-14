<?php

declare(strict_types=1);

namespace App\Repositories\Coin;

use App\Models\FeedKeyword;
use App\Repositories\BaseRepository;
use App\Repositories\Coin\Interfaces\FeedKeywordRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Class FeedKeywordRepository
 *
 * Repository class for managing feed keywords and their tags.
 *
 * @extends BaseRepository<FeedKeyword>
 */
class FeedKeywordRepository extends BaseRepository implements FeedKeywordRepositoryInterface
{
    /**
     * FeedKeywordRepository constructor.
     *
     * @param  FeedKeyword  $model  FeedKeyword model instance.
     * @return void
     */
    public function __construct(FeedKeyword $model)
    {
        parent::__construct($model);
    }

    /**
     * Find a feed keyword by its ID, including associated tags.
     *
     * @param  int  $id  Feed keyword ID.
     */
    public function find(int $id): ?FeedKeyword
    {
        return $this->model->newQuery()->with('tags')->find($id);
    }

    /**
     * Find a feed keyword by its keyword string.
     *
     * @param  string  $keyword  The keyword string.
     */
    public function findByKeyword(string $keyword): ?FeedKeyword
    {
        return $this->model->newQuery()->where('keyword', $keyword)->first();
    }

    /**
     * Sync tags to a specific feed keyword.
     *
     * @param  int  $keywordId  Feed keyword ID.
     * @param  array<int>  $tagIds  List of tag IDs.
     */
    public function syncTags(int $keywordId, array $tagIds): void
    {
        $keyword = $this->find($keywordId);

        if ($keyword) {
            $keyword->tags()->sync($tagIds);
        }
    }

    /**
     * Create a new feed keyword.
     *
     * @param  array<string, mixed>  $data  Keyword data.
     */
    public function create(array $data): FeedKeyword
    {
        return $this->model->newQuery()->create($data);
    }

    /**
     * Update a feed keyword by ID.
     *
     * @param  int  $id  Feed keyword ID.
     * @param  array<string, mixed>  $data  Updated keyword data.
     * @return bool True on success.
     */
    public function update(int $id, array $data): bool
    {
        $keyword = $this->find($id);

        return $keyword ? $keyword->update($data) : false;
    }

    /**
     * Delete a feed keyword by ID.
     *
     * @param  int  $id  Feed keyword ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $keyword = $this->find($id);

        return (bool) ($keyword?->delete());
    }

    /**
     * Get all feed keywords with tags.
     *
     * @return Collection<int, FeedKeyword>
     */
    public function allWithTags(): Collection
    {
        return $this->model->newQuery()->with('tags')->latest()->get();
    }

    /**
     * Delete a feed keyword and detach all related tags.
     *
     * @param  int  $id  Feed keyword ID.
     */
    public function deleteWithTags(int $id): void
    {
        $keyword = $this->model->newQuery()->with('tags')->findOrFail($id);

        $keyword->tags()->detach();

        $keyword->delete();
    }
}
