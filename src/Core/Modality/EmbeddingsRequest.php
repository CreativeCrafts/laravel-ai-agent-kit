<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;

/**
 * @param list<string> $inputs
 * @param array<string, mixed> $metadata
 */
final readonly class EmbeddingsRequest
{
    /**
     * @param list<string> $inputs
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public array $inputs,
        public ?int $dimensions = null,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Embeddings requests require a non-empty runId.');
        }

        if ($this->inputs === []) {
            throw new InvalidArgumentException('Embeddings requests require at least one input string.');
        }

        foreach ($this->inputs as $index => $input) {
            if (trim($input) === '') {
                throw new InvalidArgumentException(sprintf('Embeddings input at index %s must be non-empty.', $index));
            }
        }

        if ($this->timeout !== null && $this->timeout < 1) {
            throw new InvalidArgumentException('Embeddings request timeout must be null or >= 1.');
        }

        if ($this->dimensions !== null && $this->dimensions < 1) {
            throw new InvalidArgumentException('Embeddings dimensions must be null or >= 1.');
        }
    }
}
