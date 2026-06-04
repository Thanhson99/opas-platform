<?php

declare(strict_types=1);

namespace App\Support;

class RepositoryPathMatcher
{
    /**
     * Compare repository paths across Windows and Unix slash variants.
     */
    public static function matches(string $firstPath, string $secondPath): bool
    {
        return self::normalizeComparable($firstPath) === self::normalizeComparable($secondPath);
    }

    /**
     * Expand repository paths with common variants for exact SQL matching.
     *
     * @param  array<int, string>  $repositoryPaths
     * @return list<string>
     */
    public static function variantsForExactMatch(array $repositoryPaths): array
    {
        $pathVariants = [];

        foreach ($repositoryPaths as $repositoryPath) {
            $trimmedPath = trim($repositoryPath);

            if ($trimmedPath === '') {
                continue;
            }

            $pathVariants[] = $trimmedPath;
            $pathVariants[] = rtrim($trimmedPath, '\\/');

            if (self::isWindowsStyle($trimmedPath)) {
                $forwardSlashPath = str_replace('\\', '/', $trimmedPath);
                $backSlashPath = str_replace('/', '\\', $trimmedPath);

                $pathVariants[] = $forwardSlashPath;
                $pathVariants[] = $backSlashPath;
                $pathVariants[] = rtrim($forwardSlashPath, '/');
                $pathVariants[] = rtrim($backSlashPath, '\\');
                $pathVariants[] = strtolower($forwardSlashPath);
                $pathVariants[] = strtolower($backSlashPath);
                $pathVariants[] = rtrim(strtolower($forwardSlashPath), '/');
                $pathVariants[] = rtrim(strtolower($backSlashPath), '\\');
            }
        }

        return array_values(array_unique(array_filter(
            self::expandWindowsDriveCaseVariants($pathVariants),
            fn (string $path): bool => $path !== '',
        )));
    }

    /**
     * Normalize one repository path for comparison without changing persisted values.
     */
    public static function normalizeComparable(string $repositoryPath): string
    {
        $normalizedPath = str_replace('\\', '/', trim($repositoryPath));
        $normalizedPath = rtrim($normalizedPath, '/');

        return self::isWindowsStyle($repositoryPath)
            ? strtolower($normalizedPath)
            : $normalizedPath;
    }

    /**
     * Determine whether one path should get Windows slash variants.
     */
    public static function isWindowsStyle(string $repositoryPath): bool
    {
        return str_contains($repositoryPath, '\\') || preg_match('/^[A-Za-z]:[\/\\\\]/', $repositoryPath) === 1;
    }

    /**
     * Add uppercase and lowercase drive-letter variants for exact SQL matching.
     *
     * @param  array<int, string>  $repositoryPaths
     * @return list<string>
     */
    protected static function expandWindowsDriveCaseVariants(array $repositoryPaths): array
    {
        $pathVariants = [];

        foreach ($repositoryPaths as $repositoryPath) {
            $pathVariants[] = $repositoryPath;

            if (preg_match('/^[A-Za-z]:/', $repositoryPath) !== 1) {
                continue;
            }

            $pathVariants[] = strtolower($repositoryPath[0]).substr($repositoryPath, 1);
            $pathVariants[] = strtoupper($repositoryPath[0]).substr($repositoryPath, 1);
        }

        return $pathVariants;
    }
}
