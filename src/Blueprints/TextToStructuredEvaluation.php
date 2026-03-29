<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;

final readonly class TextToStructuredEvaluation
{
    public function __construct(
        private AgentOrchestrator $agentOrchestrator,
        private AgentRegistry $agentRegistry,
    ) {
    }

    public function evaluate(TextToStructuredEvaluationRequest $request): TextToStructuredEvaluationResult
    {
        $this->ensurePackageAgentsRegistered();

        $orchestrationResult = $this->agentOrchestrator->run(
            new OrchestrationRequest(
                entryAgent: TextToStructuredEvaluationCoordinatorAgent::KEY,
                task: sprintf('Evaluate text for subject [%s].', $request->subject),
                input: [
              'subject' => $request->subject,
              'text' => $request->text,
              'enabled_dimensions' => $request->dimensionList(),
              'prompt_name' => $request->promptName,
              'prompt_version' => $request->promptVersion,
              'prompt_variables' => $request->promptVariables,
              'store_conversation' => $request->storeConversation,
              'continue_conversation' => $request->continueConversation,
              'conversation_id' => $request->conversationId?->toString(),
              'model' => $request->model,
            ],
                metadata: $request->metadata,
                conversationId: $request->conversationId,
            ),
        );

        return $this->mapResult($orchestrationResult);
    }

    private function ensurePackageAgentsRegistered(): void
    {
        foreach (
          [
            TextToStructuredEvaluationCoordinatorAgent::class => TextToStructuredEvaluationCoordinatorAgent::KEY,
            TextToStructuredEvaluationSpecialistAgent::class => TextToStructuredEvaluationSpecialistAgent::KEY,
          ] as $agentClass => $agentKey
        ) {
            if ($this->agentRegistry->has($agentKey)) {
                continue;
            }

            $this->agentRegistry->register($agentClass);
        }
    }

    private function mapResult(OrchestrationResult $result): TextToStructuredEvaluationResult
    {
        $payload = $result->finalOutput;

        $subject = $payload['subject'] ?? null;
        $summary = $payload['summary'] ?? null;
        $recommendedAction = $payload['recommended_action'] ?? null;
        $confidence = $payload['confidence'] ?? null;
        $enabledDimensions = $payload['enabled_dimensions'] ?? null;
        $dimensions = $payload['dimensions'] ?? null;
        $promptName = $payload['prompt_name'] ?? null;
        $promptVersion = $payload['prompt_version'] ?? null;

        if (!is_string($subject) || $subject === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final subject must be a non-empty string.');
        }

        if (!is_string($summary) || $summary === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final summary must be a non-empty string.');
        }

        if (!is_string($recommendedAction) || $recommendedAction === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final recommended_action must be a non-empty string.');
        }

        if (!is_float($confidence) && !is_int($confidence)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final confidence must be numeric.');
        }

        if (!is_array($enabledDimensions) || $enabledDimensions === []) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final enabled_dimensions must be a non-empty list.');
        }

        if (!is_array($dimensions) || $dimensions === []) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final dimensions must be a non-empty object.');
        }

        if (!is_string($promptName) || $promptName === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final prompt_name must be a non-empty string.');
        }

        if ($promptVersion !== null && (!is_string($promptVersion) || $promptVersion === '')) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('final prompt_version must be null or a non-empty string.');
        }

        $resolvedEnabledDimensions = [];

        foreach ($enabledDimensions as $dimension) {
            if (!is_string($dimension) || $dimension === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('enabled_dimensions entries must be non-empty strings.');
            }

            $resolvedEnabledDimensions[] = $dimension;
        }

        $resolvedDimensions = [];

        foreach ($dimensions as $name => $dimensionPayload) {
            if (!is_string($name) || $name === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('dimension keys must be non-empty strings.');
            }

            if (!is_array($dimensionPayload)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] must be an object.', $name),
                );
            }

            $score = $dimensionPayload['score'] ?? null;
            $dimensionSummary = $dimensionPayload['summary'] ?? null;
            $evidence = $dimensionPayload['evidence'] ?? [];

            if (!is_int($score)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] score must be an integer.', $name),
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

            $resolvedDimensions[$name] = new TextToStructuredEvaluationDimensionResult(
                name: $name,
                score: $score,
                summary: $dimensionSummary,
                evidence: $resolvedEvidence,
            );
        }

        return new TextToStructuredEvaluationResult(
            orchestrationId: $result->orchestrationId,
            subject: $subject,
            summary: $summary,
            recommendedAction: $recommendedAction,
            confidence: (float)$confidence,
            enabledDimensions: $resolvedEnabledDimensions,
            dimensions: $resolvedDimensions,
            orchestrationSummary: $result->summary,
            finalAgent: $result->finalAgent,
            promptName: $promptName,
            promptVersion: $promptVersion,
            trace: $result->trace,
        );
    }
}
