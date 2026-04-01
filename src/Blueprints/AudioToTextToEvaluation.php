<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationTranscriptionAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;

final readonly class AudioToTextToEvaluation
{
    public function __construct(
        private AgentOrchestrator $agentOrchestrator,
        private AgentRegistry $agentRegistry,
    ) {
    }

    public function evaluate(AudioToTextToEvaluationRequest $request): AudioToTextToEvaluationResult
    {
        $this->ensurePackageAgentsRegistered();

        $result = $this->agentOrchestrator->run(
            new OrchestrationRequest(
                entryAgent: AudioToTextToEvaluationCoordinatorAgent::KEY,
                task: sprintf('Transcribe and evaluate audio for subject [%s].', $request->subject),
                input: [
              'subject' => $request->subject,
              'audio_reference' => $request->audioReference,
              'audio_mime_type' => $request->audioMimeType,
              'enabled_dimensions' => $request->dimensionList(),
              'transcription_prompt_name' => $request->transcriptionPromptName,
              'transcription_prompt_version' => $request->transcriptionPromptVersion,
              'transcription_prompt_variables' => $request->transcriptionPromptVariables,
              'evaluation_prompt_name' => $request->evaluationPromptName,
              'evaluation_prompt_version' => $request->evaluationPromptVersion,
              'evaluation_prompt_variables' => $request->evaluationPromptVariables,
              'store_conversation' => $request->storeConversation,
              'continue_conversation' => $request->continueConversation,
              'conversation_id' => $request->conversationId?->toString(),
              'transcription_model' => $request->transcriptionModel,
              'evaluation_model' => $request->evaluationModel,
            ],
                metadata: $request->metadata,
                conversationId: $request->conversationId,
            ),
        );

        return $this->mapResult($result);
    }

    private function ensurePackageAgentsRegistered(): void
    {
        foreach (
          [
            AudioToTextToEvaluationCoordinatorAgent::class => AudioToTextToEvaluationCoordinatorAgent::KEY,
            AudioToTextToEvaluationTranscriptionAgent::class => AudioToTextToEvaluationTranscriptionAgent::KEY,
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

    private function mapResult(OrchestrationResult $result): AudioToTextToEvaluationResult
    {
        $payload = $result->finalOutput;

        $subject = $this->requireString($payload['subject'] ?? null, 'subject');
        $audioReference = $this->requireString($payload['audio_reference'] ?? null, 'audio_reference');
        $transcript = $this->requireString($payload['transcript'] ?? null, 'transcript');
        $summary = $this->requireString($payload['summary'] ?? null, 'summary');
        $recommendedAction = $this->requireString($payload['recommended_action'] ?? null, 'recommended_action');
        $confidence = $payload['confidence'] ?? null;
        $enabledDimensions = $payload['enabled_dimensions'] ?? null;
        $dimensions = $payload['dimensions'] ?? null;
        $transcriptionPromptName = $this->requireString($payload['transcription_prompt_name'] ?? null, 'transcription_prompt_name');
        $transcriptionPromptVersion = $this->requireNullableString($payload['transcription_prompt_version'] ?? null, 'transcription_prompt_version');
        $evaluationPromptName = $this->requireString($payload['evaluation_prompt_name'] ?? null, 'evaluation_prompt_name');
        $evaluationPromptVersion = $this->requireNullableString($payload['evaluation_prompt_version'] ?? null, 'evaluation_prompt_version');

        if (!is_float($confidence) && !is_int($confidence)) {
            throw AudioToTextToEvaluationException::invalidPayload('final confidence must be numeric.');
        }

        if (!is_array($enabledDimensions) || $enabledDimensions === []) {
            throw AudioToTextToEvaluationException::invalidPayload('final enabled_dimensions must be a non-empty list.');
        }

        if (!is_array($dimensions) || $dimensions === []) {
            throw AudioToTextToEvaluationException::invalidPayload('final dimensions must be a non-empty object.');
        }

        $resolvedEnabledDimensions = [];

        foreach ($enabledDimensions as $dimension) {
            if (!is_string($dimension) || $dimension === '') {
                throw AudioToTextToEvaluationException::invalidPayload('enabled_dimensions entries must be non-empty strings.');
            }

            $resolvedEnabledDimensions[] = $dimension;
        }

        $resolvedDimensions = [];

        foreach ($dimensions as $name => $dimensionPayload) {
            if (!is_string($name) || $name === '') {
                throw AudioToTextToEvaluationException::invalidPayload('dimension keys must be non-empty strings.');
            }

            if (!is_array($dimensionPayload)) {
                throw AudioToTextToEvaluationException::invalidPayload(
                    sprintf(
                        'dimension [%s] must be an object.',
                        $name,
                    ),
                );
            }

            $score = $dimensionPayload['score'] ?? null;
            $dimensionSummary = $dimensionPayload['summary'] ?? null;
            $evidence = $dimensionPayload['evidence'] ?? [];

            if (!is_int($score)) {
                throw AudioToTextToEvaluationException::invalidPayload(
                    sprintf(
                        'dimension [%s] score must be an integer.',
                        $name,
                    ),
                );
            }

            if (!is_string($dimensionSummary) || $dimensionSummary === '') {
                throw AudioToTextToEvaluationException::invalidPayload(
                    sprintf(
                        'dimension [%s] summary must be a non-empty string.',
                        $name,
                    ),
                );
            }

            if (!is_array($evidence)) {
                throw AudioToTextToEvaluationException::invalidPayload(
                    sprintf(
                        'dimension [%s] evidence must be a list of strings.',
                        $name,
                    ),
                );
            }

            $resolvedEvidence = [];

            foreach ($evidence as $item) {
                if (!is_string($item) || $item === '') {
                    throw AudioToTextToEvaluationException::invalidPayload(
                        sprintf(
                            'dimension [%s] evidence entries must be non-empty strings.',
                            $name,
                        ),
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

        return new AudioToTextToEvaluationResult(
            orchestrationId: $result->orchestrationId,
            subject: $subject,
            audioReference: $audioReference,
            transcript: $transcript,
            summary: $summary,
            recommendedAction: $recommendedAction,
            confidence: (float)$confidence,
            enabledDimensions: $resolvedEnabledDimensions,
            dimensions: $resolvedDimensions,
            transcriptionPromptName: $transcriptionPromptName,
            transcriptionPromptVersion: $transcriptionPromptVersion,
            evaluationPromptName: $evaluationPromptName,
            evaluationPromptVersion: $evaluationPromptVersion,
            orchestrationSummary: $result->summary,
            finalAgent: $result->finalAgent,
            trace: $result->trace,
        );
    }

    private function requireString(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw AudioToTextToEvaluationException::invalidPayload(
                sprintf(
                    'final %s must be a non-empty string.',
                    $field,
                ),
            );
        }

        return $value;
    }

    private function requireNullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw AudioToTextToEvaluationException::invalidPayload(
                sprintf(
                    'final %s must be null or a non-empty string.',
                    $field,
                ),
            );
        }

        return $value;
    }
}
