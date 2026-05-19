<?php

declare(strict_types=1);

namespace App\Repositories\Coin\Interfaces;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface
{
    /**
     * Get all tags.
     *
     * @return Collection<int, Tag>
     */
    public function all(): Collection;

    /**
     * Find a tag by ID.
     *
     * @param  int  $id  Tag identifier.
     */
    public function find(int $id): ?Tag;

    /**
     * Find a tag by name.
     *
     * @param  string  $name  Tag name.
     */
    public function findByName(string $name): ?Tag;

    /**
     * Create a new tag.
     *
     * @param  array<string, mixed>  $data  Tag payload.
     */
    public function create(array $data): Tag;

    /**
     * Update a tag.
     *
     * @param  int  $id  Tag identifier.
     * @param  array<string, mixed>  $data  Tag payload.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a tag.
     *
     * @param  int  $id  Tag identifier.
     */
    public function delete(int $id): bool;

    /**
     * Get existing tag IDs or create them if not exist.
     *
     * @param  array<string>  $tagNames
     * @return array<int> Tag IDs.
     */
    public function getOrCreateTags(array $tagNames): array;
}
