<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use JsonException;

final readonly class TextToStructuredEvaluationSpecialistAgent implements Agent
{
    public const string KEY = 'text-to-structured-evaluation.specialist';

    public function __construct(
        private ProviderSelector $providerSelector,
        private ProviderRegistry $providerRegistry,
        private PromptRepository $promptRepository,
        private PromptExecutionMapper $promptExecutionMapper,
        private AiRuntime $aiRuntime,
    ) {
    }

    public function definition(): AgentDefinition
    {
        $primaryProfile = $this->providerSelector->selectDefault()->name;
        $fallbackProfiles = $this->fallbackProfiles($primaryProfile);

        return new AgentDefinition(
            key: self::KEY,
            displayName: 'Text To Structured Evaluation Specialist',
            requiredCapabilities: ['structured_output'],
            primaryProviderProfile: $primaryProfile,
            fallbackProviderProfiles: $fallbackProfiles,
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        $promptName = $this->stringPayloadValue($context, 'prompt_name');
        $promptVersion = $this->nullableStringPayloadValue($context, 'prompt_version');

        if (!$this->promptRepository->has($promptName, $promptVersion)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                sprintf('prompt [%s] with version [%s] is not registered.', $promptName, $promptVersion ?? 'latest'),
            );
        }

        $variables = $this->promptVariables($context);

        $request = $this->promptExecutionMapper->mapToExecutionRequest(
            name: $promptName,
            runId: $context->executionId,
            variables: $variables,
            version: $promptVersion,
            provider: $context->providerProfile,
            model: $this->nullableStringPayloadValue($context, 'model'),
            input: [
            'subject' => $context->payloadValue('subject'),
            'enabled_dimensions' => $context->payloadValue('enabled_dimensions', []),
          ],
            metadata: [
            'orchestration_id' => $context->orchestrationId,
            'agent_key' => $context->agent->key,
          ],
            conversationId: $this->conversationIdValue($context),
            storeConversation: (bool)$context->payloadValue('store_conversation', false),
            continueConversation: (bool)$context->payloadValue('continue_conversation', false),
        );

        $runtimeResult = $this->aiRuntime->execute($request);
        $parsed = $this->decodePayload($runtimeResult->output);

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'summary' => $parsed['summary'],
            'recommended_action' => $parsed['recommended_action'],
            'confidence' => $parsed['confidence'],
            'dimensions' => $parsed['dimensions'],
          ],
            summary: 'TextToStructuredEvaluation specialist completed structured analysis.',
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
            throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                sprintf('%s must be a non-empty string.', $key),
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
            throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                sprintf('%s must be null or a non-empty string.', $key),
            );
        }

        return $value;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function promptVariables(AgentExecutionContext $context): array
    {
        $variables = $context->payloadValue('prompt_variables', []);

        if (!is_array($variables)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('prompt_variables must be an associative array.');
        }

        $resolved = [];

        foreach ($variables as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('prompt_variables keys must be non-empty strings.');
            }

            if (!is_scalar($value) && $value !== null) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('prompt_variables[%s] must be scalar or null.', $key),
                );
            }

            $resolved[$key] = $value;
        }

        $resolved['subject'] = $this->stringPayloadValue($context, 'subject');
        $resolved['text'] = $this->stringPayloadValue($context, 'text');
        $resolved['enabled_dimensions'] = implode(', ', $this->enabledDimensions($context));

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function enabledDimensions(AgentExecutionContext $context): array
    {
        $dimensions = $context->payloadValue('enabled_dimensions', []);

        if (!is_array($dimensions) || $dimensions === []) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('enabled_dimensions must be a non-empty list of strings.');
        }

        $resolved = [];

        foreach ($dimensions as $dimension) {
            if (!is_string($dimension) || $dimension === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('enabled_dimensions entries must be non-empty strings.');
            }

            $resolved[] = $dimension;
        }

        return $resolved;
    }

    private function conversationIdValue(AgentExecutionContext $context): ?ConversationId
    {
        $conversationId = $context->payloadValue('conversation_id');

        if ($conversationId === null) {
            return null;
        }

        if (!is_string($conversationId) || $conversationId === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('conversation_id must be null or a non-empty string.');
        }

        return new ConversationId($conversationId);
    }

    /**
     * @return array{
     *   summary:string,
     *   recommended_action:string,
     *   confidence:float,
     *   dimensions:array<string, array{score:int,summary:string,evidence:list<string>}>
     * }
     */
    private function decodePayload(string $output): array
    {
        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TextToStructuredEvaluationException::invalidJson($output, $exception);
        }

        if (!is_array($decoded)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('decoded payload must be an object.');
        }

        $summary = $decoded['summary'] ?? null;
        $recommendedAction = $decoded['recommended_action'] ?? null;
        $confidence = $decoded['confidence'] ?? null;
        $dimensions = $decoded['dimensions'] ?? null;

        if (!is_string($summary) || $summary === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('summary must be a non-empty string.');
        }

        if (!is_string($recommendedAction) || $recommendedAction === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('recommended_action must be a non-empty string.');
        }

        if (!is_float($confidence) && !is_int($confidence)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('confidence must be a float between 0.0 and 1.0.');
        }

        if (!is_array($dimensions) || $dimensions === []) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('dimensions must be a non-empty object keyed by dimension name.');
        }

        $resolvedDimensions = [];

        foreach ($dimensions as $name => $dimension) {
            if (!is_string($name) || $name === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('dimension keys must be non-empty strings.');
            }

            if (!is_array($dimension)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] must be an object.', $name),
                );
            }

            $score = $dimension['score'] ?? null;
            $dimensionSummary = $dimension['summary'] ?? null;
            $evidence = $dimension['evidence'] ?? [];

            if (!is_int($score) || $score < 0 || $score > 5) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] score must be an integer between 0 and 5.', $name),
                );
            }

            if (!is_string($dimensionSummary) || $dimensionSummary === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] summary must be a non-empty string.', $name),
                );
            }

            if (!is_array($evidence)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] evidence must be a list of strings.', $name),
                );
            }

            $resolvedEvidence = [];

            foreach ($evidence as $item) {
                if (!is_string($item) || $item === '') {
                    throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                        sprintf('dimension [%s] evidence entries must be non-empty strings.', $name),
                    );
                }

                $resolvedEvidence[] = $item;
            }

            $resolvedDimensions[$name] = [
              'score' => $score,
              'summary' => $dimensionSummary,
              'evidence' => $resolvedEvidence,
            ];
        }

        return [
          'summary' => $summary,
          'recommended_action' => $recommendedAction,
          'confidence' => (float)$confidence,
          'dimensions' => $resolvedDimensions,
        ];
    }
}
