<?php

namespace Database\Seeders;

use App\Models\FeedKeyword;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Class FeedKeywordSeeder
 *
 * Seed default feed keywords and associated tags from a JSON file.
 */
class FeedKeywordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->resetDatabase();

        $data = $this->loadKeywordDataFromJson();

        if (empty($data)) {
            $this->command->warn('No data found in JSON or JSON format invalid.');

            return;
        }

        $this->seedKeywordsWithTags($data);

        $this->command->info('Feed keywords and tags seeded successfully.');
    }

    /**
     * Reset relevant tables before seeding.
     *
     * Handles differences between database drivers (MySQL, PostgreSQL, etc.).
     */
    protected function resetDatabase(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        DB::table('feed_keyword_tag')->truncate();
        FeedKeyword::truncate();
        Tag::truncate();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    /**
     * Load keyword and tag data from a JSON file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadKeywordDataFromJson(): array
    {
        $path = database_path('seeders/data/feed_keywords.json');

        if (! File::exists($path)) {
            $this->command->error("JSON file not found at: {$path}");

            return [];
        }

        $json = File::get($path);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Seed keywords and their associated tags.
     *
     * @param  array<int, array<string, mixed>>  $keywords  Keyword records from the seed file.
     */
    protected function seedKeywordsWithTags(array $keywords): void
    {
        foreach ($keywords as $item) {
            $keyword = FeedKeyword::create([
                'keyword' => $item['keyword'],
                'type' => $item['type'] ?? 'coin',
                'active' => true,
            ]);

            foreach ($item['tags'] ?? [] as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $keyword->tags()->syncWithoutDetaching($tag->id);
            }
        }
    }
}
