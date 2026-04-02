<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Support;

use InvalidArgumentException;

final readonly class StructuredEvaluationOutputNormalizationResult
{
    public const string STATUS_VALID = 'valid';
    public const string STATUS_REPAIRED = 'repaired';

    /**
     * @param array{
     *   summary:string,
     *   recommended_action:string,
     *   confidence:float,
     *   dimensions:array<string, array{score:int,summary:string,evidence:list<string>}>
     * } $payload
     */
    public function __construct(
        public string $status,
        public array $payload,
    ) {
        if (!in_array($this->status, [self::STATUS_VALID, self::STATUS_REPAIRED], true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Structured evaluation normalization status [%s] is not supported.',
                    $this->status,
                ),
            );
        }
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function wasRepaired(): bool
    {
        return $this->status === self::STATUS_REPAIRED;
    }
}
