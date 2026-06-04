<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RepositoryPathMatcher;
use Tests\TestCase;

class RepositoryPathMatcherTest extends TestCase
{
    /**
     * Confirm Windows-style repository paths are detected consistently for routing fallbacks.
     *
     * @return void
     */
    public function test_it_detects_windows_style_repository_paths(): void
    {
        self::assertTrue(RepositoryPathMatcher::isWindowsStyle('C:\\Workspaces\\OPAS'));
        self::assertTrue(RepositoryPathMatcher::isWindowsStyle('c:/workspaces/opas'));
        self::assertFalse(RepositoryPathMatcher::isWindowsStyle('/srv/workspaces/opas'));
    }

    /**
     * Confirm Windows path comparisons ignore slash and directory case variants.
     *
     * @return void
     */
    public function test_it_matches_windows_paths_across_slash_and_case_variants(): void
    {
        self::assertTrue(RepositoryPathMatcher::matches('C:\\Workspaces\\OPAS', 'c:/workspaces/opas'));
        self::assertFalse(RepositoryPathMatcher::matches('/srv/workspaces/OPAS', '/srv/workspaces/opas'));
    }
}
