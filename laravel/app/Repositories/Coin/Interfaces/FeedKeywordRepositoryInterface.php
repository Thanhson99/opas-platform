<?php

declare(strict_types=1);

namespace App\Repositories\Coin\Interfaces;

use App\Models\FeedKeyword;

interface FeedKeywordRepositoryInterface
{
    /**
     * Get a feed keyword by ID.
     *
     * @param  int  $id  Feed keyword identifier.
     */
    public function find(int $id): ?FeedKeyword;

    /**
     * Get a feed keyword by its keyword string.
     *
     * @param  string  $keyword  Feed keyword text.
     */
    public function findByKeyword(string $keyword): ?FeedKeyword;

    /**
     * Create a new feed keyword.
     *
     * @param  array<string, mixed>  $data  Feed keyword payload.
     */
    public function create(array $data): FeedKeyword;

    /**
     * Update an existing feed keyword by ID.
     *
     * @param  int  $id  Feed keyword identifier.
     * @param  array<string, mixed>  $data  Feed keyword payload.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a feed keyword by ID.
     *
     * @param  int  $id  Feed keyword identifier.
     */
    public function delete(int $id): bool;

    /**
     * Sync tags for a feed keyword.
     *
     * @param  int  $keywordId  Feed keyword identifier.
     * @param  array<int>  $tagIds  Tag identifiers to sync.
     */
    public function syncTags(int $keywordId, array $tagIds): void;
}
