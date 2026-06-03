<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use Illuminate\Support\Arr;

class AutoCodingTelegramTextService
{
    public function __construct(
        private readonly AutoCodingTelegramLocaleService $localeService,
    ) {}

    /**
     * Resolve one localized Telegram copy string from the central text catalog.
     *
     * @param  string  $key
     * @param  array<string, scalar|null>  $replace
     * @return string
     */
    public function line(string $key, array $replace = []): string
    {
        $catalog = $this->resolveCatalog();
        $locale = $this->localeService->resolveLocale();
        $fallbackLocale = $this->resolveFallbackLocale($catalog);

        $value = Arr::get($catalog, sprintf('%s.%s', $locale, $key));

        if (! is_string($value)) {
            $value = Arr::get($catalog, sprintf('%s.%s', $fallbackLocale, $key));
        }

        if (! is_string($value)) {
            return $key;
        }

        return strtr($value, $this->normalizeReplacements($replace));
    }

    /**
     * Resolve one localized Telegram copy list from the central text catalog.
     *
     * @param  string  $key
     * @param  array<string, scalar|null>  $replace
     * @return array<int, string>
     */
    public function lines(string $key, array $replace = []): array
    {
        $catalog = $this->resolveCatalog();
        $locale = $this->localeService->resolveLocale();
        $fallbackLocale = $this->resolveFallbackLocale($catalog);

        $value = Arr::get($catalog, sprintf('%s.%s', $locale, $key));

        if (! is_array($value)) {
            $value = Arr::get($catalog, sprintf('%s.%s', $fallbackLocale, $key));
        }

        if (! is_array($value)) {
            return [];
        }

        $replacements = $this->normalizeReplacements($replace);

        return array_values(array_map(
            static fn (mixed $line): string => is_string($line) ? strtr($line, $replacements) : '',
            $value
        ));
    }

    /**
     * Normalize placeholder replacements into the `:name` format used by the catalog.
     *
     * @param  array<string, scalar|null>  $replace
     * @return array<string, string>
     */
    protected function normalizeReplacements(array $replace): array
    {
        $normalized = [];

        foreach ($replace as $key => $value) {
            $normalized[':'.trim($key)] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $normalized;
    }

    /**
     * Resolve the configured Telegram text catalog into a stable array shape.
     *
     * @return array<string, mixed>
     */
    protected function resolveCatalog(): array
    {
        $catalog = config('auto_coding_telegram_text');

        if (! is_array($catalog)) {
            return [];
        }

        $normalizedCatalog = [];

        foreach ($catalog as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedCatalog[$key] = $value;
        }

        return $normalizedCatalog;
    }

    /**
     * Resolve the fallback locale key used by the Telegram text catalog.
     *
     * @param  array<string, mixed>  $catalog
     * @return string
     */
    protected function resolveFallbackLocale(array $catalog): string
    {
        $fallbackLocale = $catalog['fallback_locale'] ?? 'en';

        return is_string($fallbackLocale) && trim($fallbackLocale) !== ''
            ? trim($fallbackLocale)
            : 'en';
    }
}
