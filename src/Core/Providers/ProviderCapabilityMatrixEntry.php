<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use InvalidArgumentException;

final readonly class ProviderCapabilityMatrixEntry
{
    /**
     * @param array<string, list<string>> $stageRequirements
     */
    public function __construct(
        public string $capability,
        public string $description,
        public array $stageRequirements,
    ) {
        if ($this->capability === '') {
            throw new InvalidArgumentException('Capability matrix entries require a non-empty capability name.');
        }

        if ($this->description === '') {
            throw new InvalidArgumentException(
                sprintf(
                    'Capability matrix entry [%s] requires a non-empty description.',
                    $this->capability,
                ),
            );
        }

        if ($this->stageRequirements === []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Capability matrix entry [%s] requires at least one stage requirement.',
                    $this->capability,
                ),
            );
        }

        foreach ($this->stageRequirements as $stage => $requirements) {
            if ($stage === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'Capability matrix entry [%s] contains an empty stage key.',
                        $this->capability,
                    ),
                );
            }

            if ($requirements === []) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Capability matrix entry [%s] stage [%s] requires at least one declared provider capability.',
                        $this->capability,
                        $stage,
                    ),
                );
            }

            foreach ($requirements as $index => $requirement) {
                if ($requirement === '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Capability matrix entry [%s] stage [%s] contains an empty required capability at index [%d].',
                            $this->capability,
                            $stage,
                            $index,
                        ),
                    );
                }
            }
        }
    }

    public function isStagedWorkflow(): bool
    {
        return array_keys($this->stageRequirements) !== ['profile'];
    }

    /**
     * @return list<string>
     */
    public function requirementsForProfile(): array
    {
        return $this->stageRequirements['profile'] ?? [];
    }

    public function requiresStage(string $stage): bool
    {
        return array_key_exists($stage, $this->stageRequirements);
    }

    /**
     * @return list<string>
     */
    public function requirementsForStage(string $stage): array
    {
        return $this->stageRequirements[$stage] ?? [];
    }
}
