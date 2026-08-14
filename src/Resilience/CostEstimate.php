<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use InvalidArgumentException;

final readonly class CostEstimate
{
    public function __construct(
        public float $amountUsd,
        public string $source,
    ) {
        if ($this->amountUsd < 0) {
            throw new InvalidArgumentException('Cost estimates must be greater than or equal to zero.');
        }

        if ($this->source === '') {
            throw new InvalidArgumentException('Cost estimates require a non-empty source.');
        }
    }
}
