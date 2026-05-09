<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredEvaluationJsonSchema;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;

final readonly class TextToStructuredEvaluationSpecialistAgent implements Agent
{
    public const string KEY = 'text-to-structured-evaluation.specialist';

    public function __construct(
        private ProviderRegistry $providerRegistry,
        private PromptRepository $promptRepository,
        private PromptExecutionMapper $promptExecutionMapper,
        private AiRuntime $aiRuntime,
        private StructuredEvaluationOutputNormalizer $structuredEvaluationOutputNormalizer,
    ) {
    }

    public function definition(): AgentDefinition
    {
        $requiredCapabilities = ['text_generation', 'structured_output'];
        $primaryProfile = $this->selectPrimaryProfile($requiredCapabilities);
        $fallbackProfiles = $this->fallbackProfiles($primaryProfile, $requiredCapabilities);

        return new AgentDefinition(
            key: self::KEY,
            displayName: 'Text To Structured Evaluation Specialist',
            requiredCapabilities: $requiredCapabilities,
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
            schema: StructuredEvaluationJsonSchema::objectSchema(),
        );

        $runtimeResult = $this->aiRuntime->execute($request);

        $structured = $runtimeResult->structuredOutput;
        $usedStructuredPrimaryPath = false;
        $normalizedOutput = null;

        if (is_array($structured) && $structured !== []) {
            try {
                $normalizedOutput = $this->structuredEvaluationOutputNormalizer->normalizeFromDecodedArray($structured);
                $usedStructuredPrimaryPath = true;
            } catch (TextToStructuredEvaluationException) {
                $normalizedOutput = null;
            }
        }

        if (!$normalizedOutput instanceof StructuredEvaluationOutputNormalizationResult) {
            $normalizedOutput = $this->structuredEvaluationOutputNormalizer->normalize($runtimeResult->output);
        }

        $parsed = $normalizedOutput->payload;

        $repaired = !$usedStructuredPrimaryPath && $normalizedOutput->wasRepaired();

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'summary' => $parsed['summary'],
            'recommended_action' => $parsed['recommended_action'],
            'confidence' => $parsed['confidence'],
            'dimensions' => $parsed['dimensions'],
            'structured_evaluation_path' => $usedStructuredPrimaryPath ? 'structured_output' : 'text_normalization',
            'structured_evaluation_repaired' => $repaired,
          ],
            summary: 'TextToStructuredEvaluation specialist completed structured analysis.',
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

        throw TextToStructuredEvaluationException::invalidSpecialistPayload(
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
}
