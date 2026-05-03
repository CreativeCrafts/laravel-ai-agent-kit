<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

/**
 * Redacted summary of a Laravel AI Stores gateway call made through the package service.
 * Payloads MUST NOT include file contents or secrets.
 */
final readonly class LaravelAiStoresGatewayOperationFinished
{
    public function __construct(
        public string $operation,
        public ?string $provider,
        public ?string $storeId,
        public ?string $documentId,
        public bool $success,
        public ?string $errorClass = null,
        public ?string $errorSummary = null,
    ) {
    }
}
