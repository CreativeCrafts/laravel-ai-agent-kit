<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use InvalidArgumentException;

final readonly class AuditedProviderCapabilityMatrix
{
    /**
     * @var array<string, ProviderCapabilityMatrixEntry>
     */
    private array $entries;

    /**
     * @param iterable<ProviderCapabilityMatrixEntry> $entries
     */
    public function __construct(iterable $entries = [])
    {
        $resolvedEntries = [];

        foreach ($entries as $entry) {
            $resolvedEntries[$entry->capability] = $entry;
        }

        $this->entries = $resolvedEntries !== []
          ? $resolvedEntries
          : $this->defaultEntries();
    }

    /**
     * @return array<string, ProviderCapabilityMatrixEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function has(string $capability): bool
    {
        return array_key_exists($capability, $this->entries);
    }

    /**
     * @return list<string>
     */
    public function missingProfileRequirements(ProviderDefinition $providerDefinition, string $capability): array
    {
        return $providerDefinition->missingCapabilities(
            $this->get($capability)->requirementsForProfile(),
        );
    }

    public function get(string $capability): ProviderCapabilityMatrixEntry
    {
        $entry = $this->entries[$capability] ?? null;

        if (!$entry instanceof ProviderCapabilityMatrixEntry) {
            throw new InvalidArgumentException(
                sprintf(
                    'Audited provider capability [%s] is not defined.',
                    $capability,
                ),
            );
        }

        return $entry;
    }

    /**
     * @param array<string, ProviderDefinition> $profilesByStage
     * @return array<string, list<string>>
     */
    public function missingStageRequirements(array $profilesByStage, string $capability): array
    {
        $entry = $this->get($capability);
        $missingByStage = [];

        foreach ($entry->stageRequirements as $stage => $requirements) {
            $providerDefinition = $profilesByStage[$stage] ?? null;

            if (!$providerDefinition instanceof ProviderDefinition) {
                $missingByStage[$stage] = $requirements;

                continue;
            }

            $missingRequirements = $providerDefinition->missingCapabilities($requirements);

            if ($missingRequirements !== []) {
                $missingByStage[$stage] = $missingRequirements;
            }
        }

        return $missingByStage;
    }

    /**
     * @return list<string>
     */
    public function conformedCapabilitiesForProfile(ProviderDefinition $providerDefinition): array
    {
        $conformed = [];

        foreach ($this->entries as $entry) {
            $requirements = $entry->requirementsForProfile();

            if ($requirements === []) {
                continue;
            }

            if ($providerDefinition->missingCapabilities($requirements) === []) {
                $conformed[] = $entry->capability;
            }
        }

        return $conformed;
    }

    /**
     * @return array<string, ProviderCapabilityMatrixEntry>
     */
    private function defaultEntries(): array
    {
        return [
          'text_generation' => new ProviderCapabilityMatrixEntry(
              capability: 'text_generation',
              description: 'General one-shot or orchestrated text generation.',
              stageRequirements: [
              'profile' => ['text_generation'],
            ],
          ),
          'structured_output' => new ProviderCapabilityMatrixEntry(
              capability: 'structured_output',
              description: 'Structured-output generation in package-owned terms.',
              stageRequirements: [
              'profile' => ['text_generation', 'structured_output'],
            ],
          ),
          'audio_transcription' => new ProviderCapabilityMatrixEntry(
              capability: 'audio_transcription',
              description: 'Audio transcription in package-owned workflow terms.',
              stageRequirements: [
              'profile' => ['audio_transcription'],
            ],
          ),
          'tool_capable_execution' => new ProviderCapabilityMatrixEntry(
              capability: 'tool_capable_execution',
              description: 'Text execution where registered tools may participate safely.',
              stageRequirements: [
              'profile' => ['text_generation', 'tool_execution'],
            ],
          ),
          'memory_aware_continuation' => new ProviderCapabilityMatrixEntry(
              capability: 'memory_aware_continuation',
              description: 'Conversation-aware continuation through package memory semantics.',
              stageRequirements: [
              'profile' => ['text_generation', 'memory_continuation'],
            ],
          ),
          'text_to_structured_evaluation' => new ProviderCapabilityMatrixEntry(
              capability: 'text_to_structured_evaluation',
              description: 'Flagship text-to-structured-evaluation workflow target.',
              stageRequirements: [
              'profile' => ['text_generation', 'structured_output'],
            ],
          ),
          'audio_to_text_to_evaluation' => new ProviderCapabilityMatrixEntry(
              capability: 'audio_to_text_to_evaluation',
              description: 'Flagship staged audio-to-text-to-evaluation workflow target.',
              stageRequirements: [
              'transcription' => ['audio_transcription'],
              'evaluation' => ['text_generation', 'structured_output'],
            ],
          ),
        ];
    }
}
