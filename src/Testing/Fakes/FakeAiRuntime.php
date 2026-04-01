<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use Throwable;

final class FakeAiRuntime implements AiRuntime
{
    /**
     * @var list<ExecutionResult|Throwable|Closure(ExecutionRequest): ExecutionResult>
     */
    private array $queuedResponses = [];

    /**
     * @var list<ExecutionRequest>
     */
    private array $requests = [];

    /**
     * @param iterable<ExecutionResult|Throwable|Closure(ExecutionRequest): ExecutionResult> $queuedResponses
     */
    public function __construct(iterable $queuedResponses = [])
    {
        foreach ($queuedResponses as $queuedResponse) {
            $this->queuedResponses[] = $queuedResponse;
        }
    }

    public function execute(ExecutionRequest $request): ExecutionResult
    {
        $this->requests[] = $request;

        $queuedResponse = array_shift($this->queuedResponses);

        if ($queuedResponse instanceof Throwable) {
            throw $queuedResponse;
        }

        if ($queuedResponse instanceof ExecutionResult) {
            return $queuedResponse;
        }

        if ($queuedResponse instanceof Closure) {
            return $queuedResponse($request);
        }

        return new ExecutionResult(
            runId: $request->runId,
            output: '',
            provider: $request->provider,
            model: $request->model,
            metadata: [
            'fake_runtime' => true,
          ],
        );
    }

    public function queueResult(ExecutionResult $result): self
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
     * @param Closure(ExecutionRequest): ExecutionResult $callback
     */
    public function queueCallback(Closure $callback): self
    {
        $this->queuedResponses[] = $callback;

        return $this;
    }

    /**
     * @return list<ExecutionRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?ExecutionRequest
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
