<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;

final readonly class AudioToTextToEvaluationTranscriptionAgent implements Agent
{
    public const string KEY = 'audio-to-text-to-evaluation.transcription';

    public function __construct(
        private ProviderRegistry $providerRegistry,
        private PromptRepository $promptRepository,
        private PromptExecutionMapper $promptExecutionMapper,
        private AiRuntime $aiRuntime,
    ) {
    }

    public function definition(): AgentDefinition
    {
        $requiredCapabilities = ['audio_transcription'];
        $primaryProfile = $this->selectPrimaryProfile($requiredCapabilities);

        return new AgentDefinition(
            key: self::KEY,
            displayName: 'Audio To Text To Evaluation Transcription Specialist',
            requiredCapabilities: $requiredCapabilities,
            primaryProviderProfile: $primaryProfile,
            fallbackProviderProfiles: $this->fallbackProfiles($primaryProfile, $requiredCapabilities),
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        $promptName = $this->stringPayloadValue($context, 'transcription_prompt_name');
        $promptVersion = $this->nullableStringPayloadValue($context, 'transcription_prompt_version');

        if (!$this->promptRepository->has($promptName, $promptVersion)) {
            throw AudioToTextToEvaluationException::invalidPayload(
                sprintf(
                    'transcription prompt [%s] with version [%s] is not registered.',
                    $promptName,
                    $promptVersion ?? 'latest',
                ),
            );
        }

        $request = $this->promptExecutionMapper->mapToExecutionRequest(
            name: $promptName,
            runId: $context->executionId,
            variables: $this->promptVariables($context),
            version: $promptVersion,
            provider: $context->providerProfile,
            model: $this->nullableStringPayloadValue($context, 'transcription_model'),
            input: [
            'subject' => $context->payloadValue('subject'),
            'audio_reference' => $context->payloadValue('audio_reference'),
            'audio_mime_type' => $context->payloadValue('audio_mime_type'),
          ],
            metadata: [
            'orchestration_id' => $context->orchestrationId,
            'agent_key' => $context->agent->key,
            'transcription_stage' => true,
          ],
            conversationId: $this->conversationIdValue($context),
            storeConversation: (bool)$context->payloadValue('store_conversation', false),
            continueConversation: (bool)$context->payloadValue('continue_conversation', false),
        );

        $runtimeResult = $this->aiRuntime->execute($request);
        $transcript = trim($runtimeResult->output);

        if ($transcript === '') {
            throw AudioToTextToEvaluationException::invalidPayload('transcription output must be a non-empty string.');
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'transcript' => $transcript,
          ],
            summary: 'AudioToTextToEvaluation transcription specialist completed the transcript.',
        );
    }

    /**
     * @param list<string> $requiredCapabilities
     */
    private function selectPrimaryProfile(array $requiredCapabilities): string
    {
        foreach ($this->providerRegistry->all() as $providerName => $definition) {
            if (!$definition->enabled) {
                continue;
            }

            if ($this->supportsCapabilities($definition->capabilities, $requiredCapabilities)) {
                return $providerName;
            }
        }

        throw AudioToTextToEvaluationException::invalidPayload(
            sprintf(
                'No enabled provider supports required capabilities [%s].',
                implode(', ', $requiredCapabilities),
            ),
        );
    }

    /**
     * @param list<string> $providerCapabilities
     * @param list<string> $requiredCapabilities
     */
    private function supportsCapabilities(array $providerCapabilities, array $requiredCapabilities): bool
    {
        return array_diff($requiredCapabilities, $providerCapabilities) === [];
    }

    /**
     * @param list<string> $requiredCapabilities
     * @return list<string>
     */
    private function fallbackProfiles(string $primaryProfile, array $requiredCapabilities): array
    {
        $fallbackProfiles = [];

        foreach ($this->providerRegistry->all() as $providerName => $definition) {
            if ($providerName === $primaryProfile) {
                continue;
            }
            if (!$definition->enabled) {
                continue;
            }
            if (!$this->supportsCapabilities($definition->capabilities, $requiredCapabilities)) {
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
            throw AudioToTextToEvaluationException::invalidPayload(
                sprintf(
                    '%s must be a non-empty string.',
                    $key,
                ),
            );
        }

        return $value;
    }

    private function nullableStringPayloadValue(AgentExecutionContext $context, string $key): ?string
    {
        $value = $context->payloadValue($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw AudioToTextToEvaluationException::invalidPayload(
                sprintf(
                    '%s must be null or a non-empty string.',
                    $key,
                ),
            );
        }

        return $value;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function promptVariables(AgentExecutionContext $context): array
    {
        $variables = $context->payloadValue('transcription_prompt_variables', []);

        if (!is_array($variables)) {
            throw AudioToTextToEvaluationException::invalidPayload('transcription_prompt_variables must be an associative array.');
        }

        $resolved = [];

        foreach ($variables as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw AudioToTextToEvaluationException::invalidPayload('transcription_prompt_variables keys must be non-empty strings.');
            }

            if (!is_scalar($value) && $value !== null) {
                throw AudioToTextToEvaluationException::invalidPayload(
                    sprintf(
                        'transcription_prompt_variables[%s] must be scalar or null.',
                        $key,
                    ),
                );
            }

            $resolved[$key] = $value;
        }

        $resolved['subject'] = $this->stringPayloadValue($context, 'subject');
        $resolved['audio_reference'] = $this->stringPayloadValue($context, 'audio_reference');
        $resolved['audio_mime_type'] = $this->nullableStringPayloadValue($context, 'audio_mime_type');

        return $resolved;
    }

    private function conversationIdValue(AgentExecutionContext $context): ?ConversationId
    {
        $conversationId = $context->payloadValue('conversation_id');

        if ($conversationId === null) {
            return null;
        }

        if (!is_string($conversationId) || $conversationId === '') {
            throw AudioToTextToEvaluationException::invalidPayload('conversation_id must be null or a non-empty string.');
        }

        return new ConversationId($conversationId);
    }
}
