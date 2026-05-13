<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use Throwable;

final class FakeTranscriptionRuntime implements TranscriptionRuntime
{
    /**
     * @var list<TranscriptionResult|Throwable|Closure(TranscriptionRequest): TranscriptionResult>
     */
    private array $queuedResponses = [];

    /**
     * @var list<TranscriptionRequest>
     */
    private array $requests = [];

    /**
     * @param iterable<TranscriptionResult|Throwable|Closure(TranscriptionRequest): TranscriptionResult> $queuedResponses
     */
    public function __construct(iterable $queuedResponses = [])
    {
        foreach ($queuedResponses as $queuedResponse) {
            $this->queuedResponses[] = $queuedResponse;
        }
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        $this->requests[] = $request;

        $queuedResponse = array_shift($this->queuedResponses);

        if ($queuedResponse instanceof Throwable) {
            throw $queuedResponse;
        }

        if ($queuedResponse instanceof TranscriptionResult) {
            return $queuedResponse;
        }

        if ($queuedResponse instanceof Closure) {
            return $queuedResponse($request);
        }

        return new TranscriptionResult(
            runId: $request->runId,
            transcript: 'fake transcript',
            provider: $request->provider ?? 'fake',
            model: $request->model ?? 'fake',
            promptTokens: 0,
            completionTokens: 0,
            metadata: [
                'fake_transcription_runtime' => true,
                'audio_source' => $request->resolvedAudioSource()->safeMetadata(),
            ],
        );
    }

    public function queueResult(TranscriptionResult $result): self
    {
        $this->queuedResponses[] = $result;

        return $this;
    }

    public function queueFailure(Throwable $throwable): self
    {
        $this->queuedResponses[] = $throwable;

        return $this;
    }

    /**
     * @param Closure(TranscriptionRequest): TranscriptionResult $callback
     */
    public function queueCallback(Closure $callback): self
    {
        $this->queuedResponses[] = $callback;

        return $this;
    }

    /**
     * @return list<TranscriptionRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?TranscriptionRequest
    {
        $lastRequestIndex = array_key_last($this->requests);

        return $lastRequestIndex !== null ? $this->requests[$lastRequestIndex] : null;
    }

    public function reset(): void
    {
        $this->queuedResponses = [];
        $this->requests = [];
    }
}
