<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;
use InvalidArgumentException;

final readonly class AudioToTextToEvaluationCoordinatorAgent implements Agent
{
    public const string KEY = 'audio-to-text-to-evaluation.coordinator';
    public const string TRANSCRIPTION_SPECIALIST_KEY = 'audio-to-text-to-evaluation.transcription';

    public function __construct(
        private ProviderSelector $providerSelector,
        private ProviderRegistry $providerRegistry,
    ) {
    }

    public function definition(): AgentDefinition
    {
        $primaryProfile = $this->providerSelector->selectDefault()->name;

        return new AgentDefinition(
            key: self::KEY,
            displayName: 'Audio To Text To Evaluation Coordinator',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: $primaryProfile,
            fallbackProviderProfiles: $this->fallbackProfiles($primaryProfile),
            delegationTargets: [
            self::TRANSCRIPTION_SPECIALIST_KEY,
            TextToStructuredEvaluationCoordinatorAgent::KEY,
          ],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        $delegatedResult = $context->payloadValue('delegated_result');

        if (!is_array($delegatedResult)) {
            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_DELEGATE,
                delegation: new DelegationProposal(
                    mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
                    targetAgent: self::TRANSCRIPTION_SPECIALIST_KEY,
                    handoff: new HandoffPayload(
                        task: 'Produce a transcript for the provided audio input.',
                        reason: 'audio_transcription',
                        payload: [
                    'subject' => $context->payloadValue('subject'),
                    'audio_reference' => $context->payloadValue('audio_reference'),
                    'audio_mime_type' => $context->payloadValue('audio_mime_type'),
                    'transcription_prompt_name' => $context->payloadValue('transcription_prompt_name'),
                    'transcription_prompt_version' => $context->payloadValue('transcription_prompt_version'),
                    'transcription_prompt_variables' => $context->payloadValue('transcription_prompt_variables', []),
                    'store_conversation' => $context->payloadValue('store_conversation', false),
                    'continue_conversation' => $context->payloadValue('continue_conversation', false),
                    'conversation_id' => $context->payloadValue('conversation_id'),
                    'transcription_model' => $context->payloadValue('transcription_model'),
                  ],
                        note: 'Return one transcript payload for the supplied audio.',
                        requestedOutcome: 'One transcript payload.',
                    ),
                ),
                summary: 'AudioToTextToEvaluation coordinator delegated transcription.',
            );
        }

        if (
          array_key_exists('transcript', $delegatedResult)
          && $context->payloadValue('delegated_agent') === self::TRANSCRIPTION_SPECIALIST_KEY
        ) {
            $transcript = $delegatedResult['transcript'] ?? null;

            if (!is_string($transcript) || $transcript === '') {
                throw new InvalidArgumentException('AudioToTextToEvaluation transcription result must contain a non-empty transcript.');
            }

            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_DELEGATE,
                delegation: new DelegationProposal(
                    mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
                    targetAgent: TextToStructuredEvaluationCoordinatorAgent::KEY,
                    handoff: new HandoffPayload(
                        task: 'Evaluate the transcript with the package-owned structured evaluation schema.',
                        reason: 'text_structured_evaluation',
                        payload: [
                    'subject' => $this->stringPayloadValue($context, 'subject'),
                    'text' => $transcript,
                    'transcript' => $transcript,
                    'enabled_dimensions' => $context->payloadValue('enabled_dimensions', []),
                    'prompt_name' => $context->payloadValue('evaluation_prompt_name'),
                    'prompt_version' => $context->payloadValue('evaluation_prompt_version'),
                    'prompt_variables' => $context->payloadValue('evaluation_prompt_variables', []),
                    'store_conversation' => $context->payloadValue('store_conversation', false),
                    'continue_conversation' => $context->payloadValue('continue_conversation', false),
                    'conversation_id' => $context->payloadValue('conversation_id'),
                    'model' => $context->payloadValue('evaluation_model'),
                  ],
                        note: 'Return one structured evaluation payload for the transcript.',
                        requestedOutcome: 'One structured evaluation payload.',
                    ),
                ),
                summary: 'AudioToTextToEvaluation coordinator delegated transcript evaluation.',
            );
        }

        if ($context->payloadValue('delegated_agent') === self::TRANSCRIPTION_SPECIALIST_KEY) {
            throw AudioToTextToEvaluationException::invalidPayload(
                'transcription delegated result must contain a non-empty transcript.',
            );
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'subject' => $this->stringPayloadValue($context, 'subject'),
            'audio_reference' => $this->stringPayloadValue($context, 'audio_reference'),
            'transcript' => $this->resolvedTranscript($context),
            'summary' => $delegatedResult['summary'] ?? null,
            'recommended_action' => $delegatedResult['recommended_action'] ?? null,
            'confidence' => $delegatedResult['confidence'] ?? null,
            'dimensions' => $delegatedResult['dimensions'] ?? null,
            'enabled_dimensions' => $context->payloadValue('enabled_dimensions', []),
            'transcription_prompt_name' => $this->stringPayloadValue($context, 'transcription_prompt_name'),
            'transcription_prompt_version' => $context->payloadValue('transcription_prompt_version'),
            'evaluation_prompt_name' => $this->stringPayloadValue($context, 'evaluation_prompt_name'),
            'evaluation_prompt_version' => $context->payloadValue('evaluation_prompt_version'),
          ],
            summary: 'AudioToTextToEvaluation coordinator finalized the structured result.',
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
            throw new InvalidArgumentException(
                sprintf(
                    'AudioToTextToEvaluation coordinator payload key [%s] must be a non-empty string.',
                    $key,
                ),
            );
        }

        return $value;
    }

    private function resolvedTranscript(AgentExecutionContext $context): string
    {
        $transcript = $context->payloadValue('transcript');

        if (is_string($transcript) && $transcript !== '') {
            return $transcript;
        }

        $delegatedResult = $context->payloadValue('delegated_result');

        if (is_array($delegatedResult)) {
            $delegatedTranscript = $delegatedResult['transcript'] ?? null;

            if (is_string($delegatedTranscript) && $delegatedTranscript !== '') {
                return $delegatedTranscript;
            }
        }

        $evaluationText = $context->payloadValue('text');

        if (is_string($evaluationText) && $evaluationText !== '') {
            return $evaluationText;
        }

        throw new InvalidArgumentException(
            'AudioToTextToEvaluation coordinator requires a non-empty transcript after transcription completes.',
        );
    }
}
