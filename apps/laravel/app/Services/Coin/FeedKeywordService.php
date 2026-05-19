<?php

declare(strict_types=1);

namespace App\Services\Coin;

use App\Models\FeedKeyword;
use App\Repositories\Coin\Interfaces\FeedKeywordRepositoryInterface;
use App\Repositories\Coin\Interfaces\TagRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manage feed keywords and their tag relationships as one business workflow.
 */
class FeedKeywordService
{
    /**
     * Inject repository contracts used by the feed keyword workflow.
     *
     * @return void
     */
    public function __construct(
        private readonly FeedKeywordRepositoryInterface $keywordRepo,
        private readonly TagRepositoryInterface $tagRepo,
    ) {}

    /**
     * Create a new feed keyword with associated tags.
     *
     * @param  array<string, mixed>  $data
     * @return int The ID of the created feed keyword.
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            /** @var FeedKeyword $keyword */
            $keyword = $this->keywordRepo->create($data);

            if (! empty($data['tags']) && is_array($data['tags'])) {
                /** @var array<int, mixed> $tags */
                $tags = array_values($data['tags']);
                $tagIds = $this->tagRepo->getOrCreateTags($this->normalizeTagNames($tags));
                $this->keywordRepo->syncTags($keyword->id, $tagIds);
            }

            return $keyword->id;
        });
    }

    /**
     * Update an existing feed keyword and sync its tags.
     *
     * @param  int  $id  Feed keyword ID.
     * @param  array<string, mixed>  $data  Updated data.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data): void {
            $this->keywordRepo->update($id, $data);

            if (! empty($data['tags']) && is_array($data['tags'])) {
                /** @var array<int, mixed> $tags */
                $tags = array_values($data['tags']);
                $tagIds = $this->tagRepo->getOrCreateTags($this->normalizeTagNames($tags));
                $this->keywordRepo->syncTags($id, $tagIds);
            }
        });
    }

    /**
     * Get all feed keywords with tags.
     *
     * @return Collection<int, FeedKeyword>
     */
    public function getAllWithTags(): Collection
    {
        return $this->keywordRepo->allWithTags();
    }

    /**
     * Delete a feed keyword with its related tags.
     *
     * @param  int  $id  Feed keyword ID.
     * @return void
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->keywordRepo->deleteWithTags($id);
        });
    }

    /**
     * Normalize mixed tag input into unique non-empty tag names.
     *
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function normalizeTagNames(array $tags): array
    {
        return array_values(array_unique(array_filter(array_map(
            static function (mixed $tag): string {
                if (is_scalar($tag) || $tag === null) {
                    return trim((string) $tag);
                }

                throw new \InvalidArgumentException('Tag must be scalar or null.');
            },
            $tags
        ))));
    }
}
