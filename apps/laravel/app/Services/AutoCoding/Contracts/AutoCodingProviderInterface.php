<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Contracts;

interface AutoCodingProviderInterface
{
    /**
     * Return the internal provider key used for reporting.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Prepare a provider response for the current coding task context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function plan(array $context): array;
}
