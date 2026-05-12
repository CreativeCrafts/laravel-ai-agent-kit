<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
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
        $customSchema = (bool)$context->payloadValue('custom_evaluation_schema', false);

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
            'audio_evaluation_stage' => 'evaluation',
            'custom_evaluation_schema' => $customSchema,
          ],
            conversationId: $this->conversationIdValue($context),
            storeConversation: (bool)$context->payloadValue('store_conversation', false),
            continueConversation: (bool)$context->payloadValue('continue_conversation', false),
            schema: $customSchema ? $context->payloadValue('evaluation_schema') : StructuredEvaluationJsonSchema::objectSchema(),
        );

        $runtimeResult = $this->aiRuntime->execute($request);

        if ($customSchema) {
            $structured = $runtimeResult->structuredOutput;

            if (!is_array($structured) || $structured === []) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    'evaluation stage expected non-empty structured output for the custom audio evaluation schema.',
                );
            }

            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_COMPLETE,
                output: [
                  'summary' => $this->summaryFromStructuredOutput($structured),
                  'recommended_action' => $this->recommendedActionFromStructuredOutput($structured),
                  'confidence' => $this->confidenceFromStructuredOutput($structured),
                  'dimensions' => $this->dimensionsFromStructuredOutput($structured),
                  'structured_output' => $structured,
                  'metadata' => [
                    'structured_evaluation_path' => 'structured_output',
                    'custom_evaluation_schema' => true,
                  ],
                  'evaluation_provider' => $runtimeResult->provider,
                  'evaluation_model' => $runtimeResult->model,
                  'usage' => $this->usagePayload($runtimeResult),
                ],
                summary: 'TextToStructuredEvaluation specialist completed custom schema analysis.',
            );
        }

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
            'structured_output' => $parsed,
            'metadata' => [
              'structured_evaluation_path' => $usedStructuredPrimaryPath ? 'structured_output' : 'text_normalization',
              'structured_evaluation_repaired' => $repaired,
              'custom_evaluation_schema' => false,
            ],
            'structured_evaluation_path' => $usedStructuredPrimaryPath ? 'structured_output' : 'text_normalization',
            'structured_evaluation_repaired' => $repaired,
            'evaluation_provider' => $runtimeResult->provider,
            'evaluation_model' => $runtimeResult->model,
            'usage' => $this->usagePayload($runtimeResult),
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

    /**
     * @return array<string, int>
     */
    private function usagePayload(ExecutionResult $result): array
    {
        return $result->usage;
    }

    /**
     * @param array<string, mixed> $structured
     */
    private function summaryFromStructuredOutput(array $structured): string
    {
        $summary = $structured['summary'] ?? $structured['analysis'] ?? $structured['result'] ?? 'Custom structured audio evaluation completed.';

        if (!is_string($summary) || $summary === '') {
            return 'Custom structured audio evaluation completed.';
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $structured
     */
    private function recommendedActionFromStructuredOutput(array $structured): string
    {
        $action = $structured['recommended_action'] ?? $structured['recommendedAction'] ?? 'Review structured_output for the schema-specific result.';

        if (!is_string($action) || $action === '') {
            return 'Review structured_output for the schema-specific result.';
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $structured
     */
    private function confidenceFromStructuredOutput(array $structured): float
    {
        $confidence = $structured['confidence'] ?? 1.0;

        if (!is_int($confidence) && !is_float($confidence)) {
            return 1.0;
        }

        return max(0.0, min(1.0, (float)$confidence));
    }

    /**
     * @param array<string, mixed> $structured
     * @return array<string, array{name:string,score:int,summary:string,evidence:list<string>}>
     */
    private function dimensionsFromStructuredOutput(array $structured): array
    {
        $dimensions = $structured['dimensions'] ?? null;

        if (!is_array($dimensions) || $dimensions === []) {
            return [
                'custom_schema' => [
                    'name' => 'custom_schema',
                    'score' => 1,
                    'summary' => 'Custom schema structured output was returned.',
                    'evidence' => ['structured_output'],
                ],
            ];
        }

        $resolved = [];

        foreach ($dimensions as $name => $payload) {
            if (!is_string($name) || $name === '' || !is_array($payload)) {
                continue;
            }

            $score = $payload['score'] ?? 1;
            $summary = $payload['summary'] ?? 'Custom schema dimension returned.';
            $evidence = $payload['evidence'] ?? ['structured_output'];

            if (!is_int($score)) {
                $score = 1;
            }

            if (!is_string($summary) || $summary === '') {
                $summary = 'Custom schema dimension returned.';
            }

            if (!is_array($evidence)) {
                $evidence = ['structured_output'];
            }

            $resolvedEvidence = [];

            foreach ($evidence as $item) {
                if (is_string($item) && $item !== '') {
                    $resolvedEvidence[] = $item;
                }
            }

            $resolved[$name] = [
                'name' => $name,
                'score' => $score,
                'summary' => $summary,
                'evidence' => $resolvedEvidence !== [] ? $resolvedEvidence : ['structured_output'],
            ];
        }

        return $resolved !== [] ? $resolved : [
            'custom_schema' => [
                'name' => 'custom_schema',
                'score' => 1,
                'summary' => 'Custom schema structured output was returned.',
                'evidence' => ['structured_output'],
            ],
        ];
    }
}
