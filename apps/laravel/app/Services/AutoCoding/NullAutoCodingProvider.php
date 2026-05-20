<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;

class NullAutoCodingProvider implements AutoCodingProviderInterface
{
    /**
     * Return the internal provider key used for reporting.
     *
     * @return string
     */
    public function name(): string
    {
        return 'null';
    }

    /**
     * Prepare a provider response for the current coding task context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function plan(array $context): array
    {
        return [
            'status' => 'skipped',
            'provider' => $this->name(),
            'message' => 'No external AI provider is configured for local auto coding yet.',
            'task_summary' => $context['task_summary'] ?? null,
        ];
    }
}
