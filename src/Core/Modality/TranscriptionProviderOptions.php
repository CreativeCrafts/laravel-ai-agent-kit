<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;

final readonly class TranscriptionProviderOptions
{
    public const string CHUNKING_STRATEGY_AUTO = 'auto';

    public function __construct(
        public ?string $chunkingStrategy = null,
    ) {
        if ($this->chunkingStrategy === null) {
            return;
        }

        if ($this->chunkingStrategy !== self::CHUNKING_STRATEGY_AUTO) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported transcription chunking strategy [%s]. Supported values: [%s].',
                    $this->chunkingStrategy,
                    self::CHUNKING_STRATEGY_AUTO,
                ),
            );
        }
    }

    public function hasChunkingStrategy(): bool
    {
        return $this->chunkingStrategy !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toProviderOptions(): array
    {
        if ($this->chunkingStrategy === null) {
            return [];
        }

        return [
            'chunking_strategy' => $this->chunkingStrategy,
        ];
    }
}
