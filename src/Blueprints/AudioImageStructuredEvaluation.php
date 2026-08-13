<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioImageStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\EvaluationImageAttachmentFactory;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;

final readonly class AudioImageStructuredEvaluation
{
    private const string CAPABILITY_AUDIO_TRANSCRIPTION = 'audio_transcription';
    private const string CAPABILITY_TEXT_GENERATION = 'text_generation';
    private const string CAPABILITY_STRUCTURED_OUTPUT = 'structured_output';
    private const string CAPABILITY_IMAGE_INPUT = 'image_input';
    private const string CAPABILITY_VISION = 'vision';

    public function __construct(
        private TranscriptionRuntime $transcriptionRuntime,
        private AiRuntime $runtime,
        private ProviderRegistry $providerRegistry,
        private EvaluationImageAttachmentFactory $imageAttachmentFactory = new EvaluationImageAttachmentFactory(),
    ) {
    }

    public function evaluate(AudioImageStructuredEvaluationRequest $request): AudioImageStructuredEvaluationResult
    {
        $this->assertTranscriptionProviderCapabilities($request->transcriptionProvider);
        $this->assertEvaluationProviderCapabilities($request->evaluationProvider);

        $transcription = $this->transcriptionRuntime->transcribe(
            TranscriptionRequest::fromAudioSource(
                runId: $request->runId . ':transcription',
                audioSource: $request->audio,
                language: $request->language,
                diarize: $request->diarize,
                timeout: $request->transcriptionTimeout,
                provider: $request->transcriptionProvider,
                model: $request->transcriptionModel,
                metadata: array_merge($request->metadata, [
              'workflow' => 'audio_image_structured_evaluation',
              'workflow_stage' => 'transcription',
              'parent_run_id' => $request->runId,
            ]),
                prompt: $request->transcriptionPrompt,
                providerOptions: $request->transcriptionProviderOptions,
            ),
        );

        if ($transcription->transcript === '' && !$request->allowEmptyTranscript) {
            throw AudioImageStructuredEvaluationException::emptyTranscript();
        }

        $evaluation = $this->runtime->execute(
            new ExecutionRequest(
                runId: $request->runId . ':evaluation',
                prompt: $this->evaluationPrompt($request, $transcription->transcript),
                instructions: $request->instructions,
                provider: $request->evaluationProvider,
                model: $request->evaluationModel,
                metadata: array_merge($request->metadata, [
              'workflow' => 'audio_image_structured_evaluation',
              'workflow_stage' => 'evaluation',
              'parent_run_id' => $request->runId,
              'audio_source' => $request->audio->safeMetadata(),
              'image_input' => $request->image->safeMetadata(),
              'transcription_provider' => $transcription->provider,
              'transcription_model' => $transcription->model,
            ]),
                timeout: $request->evaluationTimeout,
                generationOptions: $request->generationOptions,
                schema: $this->imageAttachmentFactory->executionSchema($request->schema),
                attachments: [$this->imageAttachmentFactory->make($request->image)],
                strictStructuredOutput: $request->strictStructuredOutput,
            ),
        );

        return new AudioImageStructuredEvaluationResult(
            runId: $request->runId,
            transcript: $transcription->transcript,
            structuredOutput: $evaluation->structuredOutput ?? [],
            output: $evaluation->output,
            usage: [
            'transcription_prompt_tokens' => $transcription->promptTokens,
            'transcription_completion_tokens' => $transcription->completionTokens,
            'evaluation_prompt_tokens' => $evaluation->usage['prompt_tokens'] ?? 0,
            'evaluation_completion_tokens' => $evaluation->usage['completion_tokens'] ?? 0,
            'evaluation_total_tokens' => $evaluation->usage['total_tokens'] ?? 0,
          ],
            metadata: [
            'audio_source' => $request->audio->safeMetadata(),
            'image_input' => $request->image->safeMetadata(),
            'transcription_metadata' => $transcription->metadata,
            'evaluation_metadata' => $evaluation->metadata,
          ],
            transcriptionProvider: $transcription->provider,
            transcriptionModel: $transcription->model,
            evaluationProvider: $evaluation->provider,
            evaluationModel: $evaluation->model,
        );
    }

    private function assertTranscriptionProviderCapabilities(?string $provider): void
    {
        if ($provider === null || !$this->providerRegistry->has($provider)) {
            return;
        }

        $definition = $this->providerRegistry->get($provider);
        $missing = $definition->missingCapabilities([self::CAPABILITY_AUDIO_TRANSCRIPTION]);

        if ($missing !== []) {
            throw AudioImageStructuredEvaluationException::missingProviderCapabilities($provider, $missing);
        }
    }

    private function assertEvaluationProviderCapabilities(?string $provider): void
    {
        if ($provider === null || !$this->providerRegistry->has($provider)) {
            return;
        }

        $definition = $this->providerRegistry->get($provider);
        $missing = $definition->missingCapabilities([
            self::CAPABILITY_TEXT_GENERATION,
            self::CAPABILITY_STRUCTURED_OUTPUT,
        ]);

        if ($missing !== []) {
            throw AudioImageStructuredEvaluationException::missingProviderCapabilities($provider, $missing);
        }

        if (!$definition->supportsCapability(self::CAPABILITY_IMAGE_INPUT) && !$definition->supportsCapability(self::CAPABILITY_VISION)) {
            throw AudioImageStructuredEvaluationException::missingImageCapability($provider);
        }
    }

    private function evaluationPrompt(AudioImageStructuredEvaluationRequest $request, string $transcript): string
    {
        if ($request->evaluationInputTemplate === null) {
            return $request->evaluationPrompt . "\n\nTranscript:\n" . $transcript;
        }

        return $this->renderCustomEvaluationInput($request->evaluationInputTemplate, $request->evaluationPrompt, $transcript);
    }

    private function renderCustomEvaluationInput(string $template, string $evaluationPrompt, string $transcript): string
    {
        return strtr($template, [
            '{{evaluation_prompt}}' => $evaluationPrompt,
            '{{transcript}}' => $transcript,
        ]);
    }
}
