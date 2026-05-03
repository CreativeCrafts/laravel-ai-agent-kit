<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

/**
 * Redacted summary of a Laravel AI Files gateway call made through the package service.
 * Payloads MUST NOT include file bodies or secrets.
 */
final readonly class LaravelAiFilesGatewayOperationFinished
{
    public function __construct(
        public string $operation,
        public ?string $provider,
        public ?string $resourceId,
        public bool $success,
        public ?string $errorClass = null,
        public ?string $errorSummary = null,
    ) {
    }
}
