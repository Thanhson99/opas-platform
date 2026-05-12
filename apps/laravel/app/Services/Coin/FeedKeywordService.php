<?php

declare(strict_types=1);

namespace App\Services\Coin;

use App\Models\FeedKeyword;
use App\Repositories\Coin\FeedKeywordRepository;
use App\Repositories\Coin\TagRepository;
use Illuminate\Support\Collection;

/**
 * Class FeedKeywordService
 *
 * Service class for managing feed keywords and their associated tags.
 */
class FeedKeywordService
{
    /**
     * FeedKeywordService constructor.
     */
    public function __construct(
        protected FeedKeywordRepository $keywordRepo,
        protected TagRepository $tagRepo
    ) {}

    /**
     * Create a new feed keyword with associated tags.
     *
     * @param  array<string, mixed>  $data
     * @return int The ID of the created feed keyword.
     */
    public function create(array $data): int
    {
        /** @var FeedKeyword $keyword */
        $keyword = $this->keywordRepo->create($data);

        if (! empty($data['tags']) && is_array($data['tags'])) {
            /** @var array<int, string> $tagNames */
            $tagNames = array_map(
                static function (mixed $tag): string {
                    if (is_scalar($tag) || $tag === null) {
                        return (string) $tag;
                    }

                    throw new \InvalidArgumentException('Tag must be scalar or null.');
                },
                $data['tags']
            );

            $tagIds = $this->tagRepo->getOrCreateTags($tagNames);
            $this->keywordRepo->syncTags($keyword->id, $tagIds);
        }

        return $keyword->id;
    }

    /**
     * Update an existing feed keyword and sync its tags.
     *
     * @param  int  $id  Feed keyword ID.
     * @param  array<string, mixed>  $data  Updated data.
     */
    public function update(int $id, array $data): void
    {
        $this->keywordRepo->update($id, $data);

        if (! empty($data['tags']) && is_array($data['tags'])) {
            /** @var array<int, string> $tagNames */
            $tagNames = array_map(
                static function (mixed $tag): string {
                    if (is_scalar($tag) || $tag === null) {
                        return (string) $tag;
                    }

                    throw new \InvalidArgumentException('Tag must be scalar or null.');
                },
                $data['tags']
            );

            $tagIds = $this->tagRepo->getOrCreateTags($tagNames);
            $this->keywordRepo->syncTags($id, $tagIds);
        }
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
     */
    public function delete(int $id): void
    {
        $this->keywordRepo->deleteWithTags($id);
    }
}
