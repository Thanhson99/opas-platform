<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use Illuminate\Contracts\Foundation\Application;

class AutoCodingProviderResolver
{
    /**
     * Inject the application container used to resolve provider instances.
     *
     * @return void
     */
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Resolve a local auto-coding provider by override or default config.
     *
     * @param  string|null  $providerName
     * @return AutoCodingProviderInterface
     */
    public function resolve(?string $providerName = null): AutoCodingProviderInterface
    {
        $resolvedName = is_string($providerName) && $providerName !== ''
            ? $providerName
            : $this->defaultProviderName();

        return match ($resolvedName) {
            'codex' => $this->app->make(CodexCliAutoCodingProvider::class),
            'ollama' => $this->app->make(OllamaAutoCodingProvider::class),
            default => new NullAutoCodingProvider,
        };
    }

    /**
     * Resolve the configured default provider name.
     *
     * @return string
     */
    protected function defaultProviderName(): string
    {
        $provider = config('opas.auto_coding.provider');

        return is_string($provider) && $provider !== '' ? $provider : 'null';
    }
}
