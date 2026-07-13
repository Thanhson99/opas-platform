<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DouyinKeyword;
use Illuminate\Database\Seeder;

class DouyinKeywordSeeder extends Seeder
{
    /**
     * Seed preset Chinese keywords for Douyin workflows.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->keywords() as $priority => $name) {
            DouyinKeyword::query()->updateOrCreate(
                ['name' => $name],
                [
                    'source' => 'preset',
                    'priority' => $priority,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Return the default keyword preset list.
     *
     * @return list<string>
     */
    private function keywords(): array
    {
        return [
            '美女跳舞',
            '小姐姐跳舞',
            '穿搭',
            '美妆',
            '探店',
            '街拍',
            '搞笑',
            '宠物',
            '美食',
            '健身',
            '旅行',
            '音乐',
            '舞蹈',
            '剧情',
            '开箱',
        ];
    }
}
