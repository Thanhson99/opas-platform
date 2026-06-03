<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaAutoCodingProvider implements AutoCodingProviderInterface
{
    public function __construct(
        private readonly PromptContextAssembler $promptContextAssembler,
    ) {}

    /**
     * Return the internal provider key used for reporting.
     *
     * @return string
     */
    public function name(): string
    {
        return 'ollama';
    }

    /**
     * Prepare a provider response for the current coding task context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function plan(array $context): array
    {
        $promptPackage = $this->promptContextAssembler->assemble($context);
        $baseUrl = rtrim($this->resolveBaseUrl(), '/');
        $model = $this->resolveModel($context);
        $timeoutSeconds = $this->resolveTimeoutSeconds();

        try {
            $response = Http::timeout($timeoutSeconds)->acceptJson()->post($baseUrl.'/api/chat', [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $promptPackage['system_prompt'],
                    ],
                    [
                        'role' => 'user',
                        'content' => $promptPackage['user_prompt'],
                    ],
                ],
            ]);

            if ($response->failed()) {
                return [
                    'status' => 'failed',
                    'provider' => $this->name(),
                    'model' => $model,
                    'message' => 'Ollama returned a non-success response.',
                    'http_status' => $response->status(),
                    'prompt_package' => $promptPackage,
                ];
            }

            $json = $response->json();
            $content = null;

            if (is_array($json)) {
                $message = $json['message'] ?? null;
                if (is_array($message)) {
                    $messageContent = $message['content'] ?? null;
                    $content = is_string($messageContent) ? $messageContent : null;
                }
            } else {
                $json = [];
            }

            return [
                'status' => 'completed',
                'provider' => $this->name(),
                'model' => $model,
                'prompt_package' => $promptPackage,
                'response' => $json,
                'content' => $content,
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'failed',
                'provider' => $this->name(),
                'model' => $model,
                'message' => $throwable->getMessage(),
                'prompt_package' => $promptPackage,
            ];
        }
    }

    /**
     * Resolve the configured Ollama base URL.
     *
     * @return string
     */
    protected function resolveBaseUrl(): string
    {
        $baseUrl = config('opas.auto_coding.providers.ollama.base_url');

        return is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : '';
    }

    /**
     * Resolve the configured Ollama model name.
     *
     * @param  array<string, mixed>  $context
     * @return string
     */
    protected function resolveModel(array $context): string
    {
        $providerOptions = $context['provider_options'] ?? null;
        if (is_array($providerOptions)) {
            $overrideModel = $providerOptions['model'] ?? null;
            if (is_string($overrideModel) && $overrideModel !== '') {
                return $overrideModel;
            }
        }

        $model = config('opas.auto_coding.providers.ollama.model');

        return is_string($model) && $model !== '' ? $model : '';
    }

    /**
     * Resolve the configured Ollama timeout in seconds.
     *
     * @return int
     */
    protected function resolveTimeoutSeconds(): int
    {
        $timeout = config('opas.auto_coding.providers.ollama.timeout_seconds');

        return is_int($timeout) ? $timeout : 0;
    }
}
