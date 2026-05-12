<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionPromptException;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use Throwable;

final readonly class AudioToTextToEvaluationTranscriptionAgent implements Agent
{
    public const string KEY = 'audio-to-text-to-evaluation.transcription';

    public function __construct(
        private ProviderRegistry $providerRegistry,
        private PromptRepository $promptRepository,
        private PromptExecutionMapper $promptExecutionMapper,
        private AiRuntime $aiRuntime,
        private TranscriptionRuntime $transcriptionRuntime,
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

        $audioReference = $this->stringPayloadValue($context, 'audio_reference');
        $audioMimeType = $this->nullableStringPayloadValue($context, 'audio_mime_type');
        $promptVariables = $this->promptVariables($context);
        $renderedPrompt = $this->promptRepository->render($promptName, $promptVariables, $promptVersion);

        $runtimeResult = $this->tryTranscriptionRuntimeResult(
            context: $context,
            audioReference: $audioReference,
            audioMimeType: $audioMimeType,
            promptName: $promptName,
            promptVersion: $promptVersion,
            renderedPrompt: $renderedPrompt,
            providerProfile: $context->providerProfile,
            transcriptionModel: $this->nullableStringPayloadValue($context, 'transcription_model'),
        );

        if ($runtimeResult instanceof TranscriptionResult) {
            $transcript = trim($runtimeResult->transcript);

            if ($transcript === '') {
                throw AudioToTextToEvaluationException::invalidPayload('transcription output must be a non-empty string.');
            }

            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_COMPLETE,
                output: [
                    'transcript' => $transcript,
                    'segments' => $this->segmentPayloads($runtimeResult),
                    'transcription_provider' => $runtimeResult->provider,
                    'transcription_model' => $runtimeResult->model,
                    'usage' => [
                        'prompt_tokens' => $runtimeResult->promptTokens,
                        'completion_tokens' => $runtimeResult->completionTokens,
                    ],
                ],
                summary: 'AudioToTextToEvaluation transcription specialist completed the transcript.',
            );
        }

        $request = $this->promptExecutionMapper->mapToExecutionRequest(
            name: $promptName,
            runId: $context->executionId,
            variables: $promptVariables,
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

        $aiRuntimeResult = $this->aiRuntime->execute($request);
        $transcript = trim($aiRuntimeResult->output);

        if ($transcript === '') {
            throw AudioToTextToEvaluationException::invalidPayload('transcription output must be a non-empty string.');
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
                'transcript' => $transcript,
                'segments' => [],
                'transcription_provider' => $aiRuntimeResult->provider,
                'transcription_model' => $aiRuntimeResult->model,
                'usage' => $aiRuntimeResult->usage,
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

    private function tryTranscriptionRuntimeResult(
        AgentExecutionContext $context,
        string $audioReference,
        ?string $audioMimeType,
        string $promptName,
        ?string $promptVersion,
        string $renderedPrompt,
        string $providerProfile,
        ?string $transcriptionModel,
    ): ?TranscriptionResult {
        $normalized = $this->normalizeBase64AudioPayload($audioReference, $audioMimeType);

        if ($normalized === null) {
            return null;
        }

        try {
            $result = $this->transcriptionRuntime->transcribe(
                new TranscriptionRequest(
                    runId: $context->executionId,
                    base64Audio: $normalized['base64'],
                    mimeType: $normalized['mime_type'],
                    timeout: 120,
                    provider: $providerProfile,
                    model: $transcriptionModel,
                    metadata: [
                        'orchestration_id' => $context->orchestrationId,
                        'agent_key' => $context->agent->key,
                        'transcription_stage' => true,
                        'transcription_prompt_name' => $promptName,
                        'transcription_prompt_version' => $promptVersion,
                    ],
                    prompt: $renderedPrompt,
                ),
            );

            return trim($result->transcript) !== '' ? $result : null;
        } catch (UnsupportedTranscriptionPromptException $exception) {
            throw $exception;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{base64: string, mime_type: ?string}|null
     */
    private function normalizeBase64AudioPayload(string $reference, ?string $mimeType): ?array
    {
        $candidate = trim($reference);

        if ($candidate === '') {
            return null;
        }

        if (str_starts_with($candidate, 'data:')) {
            $commaPosition = strpos($candidate, ',');

            if ($commaPosition === false) {
                return null;
            }

            $header = substr($candidate, 5, $commaPosition - 5);
            $payload = substr($candidate, $commaPosition + 1);

            if ($payload === '') {
                return null;
            }

            if (str_contains($header, ';base64')) {
                $mimePart = strstr($header, ';', true);

                if (is_string($mimePart) && $mimePart !== '') {
                    $mimeType ??= $mimePart;
                }
            }

            $candidate = $payload;
        }

        $decoded = base64_decode($candidate, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        return [
            'base64' => $candidate,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * @return list<array{text:string,speaker:string,start_seconds:float,end_seconds:float}>
     */
    private function segmentPayloads(TranscriptionResult $result): array
    {
        $segments = [];

        foreach ($result->segments as $segment) {
            $segments[] = [
                'text' => $segment->text,
                'speaker' => $segment->speaker,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
            ];
        }

        return $segments;
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
