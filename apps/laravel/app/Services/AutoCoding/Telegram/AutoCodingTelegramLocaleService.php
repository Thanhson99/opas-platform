<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

class AutoCodingTelegramLocaleService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
    ) {}

    /**
     * Resolve the configured Telegram locale.
     *
     * @return string
     */
    public function resolveLocale(): string
    {
        $locale = $this->runtimeConfigService->getRuntimeConfig()['locale'] ?? 'en';

        if (! is_string($locale)) {
            return 'en';
        }

        return trim(strtolower($locale)) === 'vi' ? 'vi' : 'en';
    }

    /**
     * Determine whether Telegram copy should be rendered in Vietnamese.
     *
     * @return bool
     */
    public function isVietnamese(): bool
    {
        return $this->resolveLocale() === 'vi';
    }
}
