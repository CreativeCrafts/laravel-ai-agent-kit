<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;
use InvalidArgumentException;

final readonly class TextToStructuredEvaluationCoordinatorAgent implements Agent
{
    public const string KEY = 'text-to-structured-evaluation.coordinator';
    public const string SPECIALIST_KEY = 'text-to-structured-evaluation.specialist';

    public function __construct(
        private ProviderSelector $providerSelector,
        private ProviderRegistry $providerRegistry,
    ) {
    }

    public function definition(): AgentDefinition
    {
        $primaryProfile = $this->providerSelector->selectDefault()->name;
        $fallbackProfiles = $this->fallbackProfiles($primaryProfile);

        return new AgentDefinition(
            key: self::KEY,
            displayName: 'Text To Structured Evaluation Coordinator',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: $primaryProfile,
            fallbackProviderProfiles: $fallbackProfiles,
            delegationTargets: [self::SPECIALIST_KEY],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        $delegatedResult = $context->payloadValue('delegated_result');

        if (is_array($delegatedResult)) {
            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_COMPLETE,
                output: [
                'subject' => $this->stringPayloadValue($context, 'subject'),
                'summary' => $delegatedResult['summary'] ?? null,
                'recommended_action' => $delegatedResult['recommended_action'] ?? null,
                'confidence' => $delegatedResult['confidence'] ?? null,
                'dimensions' => $delegatedResult['dimensions'] ?? null,
                'enabled_dimensions' => $context->payloadValue('enabled_dimensions', []),
                'prompt_name' => $this->stringPayloadValue($context, 'prompt_name'),
                'prompt_version' => $context->payloadValue('prompt_version'),
                'transcript' => $this->resolvedTranscript($context),
              ],
                summary: 'TextToStructuredEvaluation coordinator finalized the structured result.',
            );
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
                targetAgent: self::SPECIALIST_KEY,
                handoff: new HandoffPayload(
                    task: 'Produce a structured evaluation for the provided text.',
                    reason: 'structured_text_evaluation',
                    payload: [
                'subject' => $context->payloadValue('subject'),
                'text' => $context->payloadValue('text'),
                'transcript' => $context->payloadValue('transcript'),
                'enabled_dimensions' => $context->payloadValue('enabled_dimensions', []),
                'prompt_name' => $context->payloadValue('prompt_name'),
                'prompt_version' => $context->payloadValue('prompt_version'),
                'prompt_variables' => $context->payloadValue('prompt_variables', []),
                'store_conversation' => $context->payloadValue('store_conversation', false),
                'continue_conversation' => $context->payloadValue('continue_conversation', false),
                'conversation_id' => $context->payloadValue('conversation_id'),
                'model' => $context->payloadValue('model'),
              ],
                    note: 'Return one structured evaluation payload using the fixed package schema.',
                    requestedOutcome: 'One structured evaluation payload.',
                ),
            ),
            summary: 'TextToStructuredEvaluation coordinator delegated specialist analysis.',
        );
    }

    /**
     * @return list<string>
     */
    private function fallbackProfiles(string $primaryProfile): array
    {
        $fallbackProfiles = [];

        foreach ($this->providerRegistry->all() as $providerName => $definition) {
            if ($providerName === $primaryProfile) {
                continue;
            }
            if (!$definition->enabled) {
                continue;
            }
            $fallbackProfiles[] = $providerName;
        }

        return $fallbackProfiles;
    }

    private function stringPayloadValue(AgentExecutionContext $context, string $key): string
    {
        $value = $context->payloadValue($key);

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Coordinator payload key [%s] must be a non-empty string.', $key));
        }

        return $value;
    }

    private function resolvedTranscript(AgentExecutionContext $context): string
    {
        $transcript = $context->payloadValue('transcript');

        if (is_string($transcript) && $transcript !== '') {
            return $transcript;
        }

        $text = $context->payloadValue('text');

        if (is_string($text) && $text !== '') {
            return $text;
        }

        throw new InvalidArgumentException('TextToStructuredEvaluation coordinator requires a non-empty transcript or text payload.');
    }
}
