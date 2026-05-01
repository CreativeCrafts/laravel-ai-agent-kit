<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\SchemaResolutionException;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamChunkEmitted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategoryResolver;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\RequestObservabilityKeys;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Files\File;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;
use Generator;

final readonly class SdkAiRuntime implements AiRuntime, StreamingAiRuntime
{
    public function __construct(
        private SdkToolMaterializer $toolMaterializer,
        private ProviderToolMaterializer $providerToolMaterializer,
        private RuntimeConversationMemoryBridge $runtimeConversationMemoryBridge,
        private RuntimeBudgetEnforcer $runtimeBudgetEnforcer,
        private Container $container,
        private ?Dispatcher $events = null,
        private ?Redactor $redactor = null,
    ) {
    }

    public function execute(ExecutionRequest $request): ExecutionResult
    {
        try {
            $estimatedCostUsd = $this->estimatedCostUsd($request);
        } catch (RuntimeBudgetExceededException $exception) {
            $this->reportRuntimeFailure($request, $exception);

            throw $exception;
        }

        try {
            $projectedConversation = $this->runtimeConversationMemoryBridge->project($request);
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure($request, $throwable);
        }

        try {
            $materializedTools = [
                ...$this->toolMaterializer->materialize($request->toolNames),
                ...$this->providerToolMaterializer->materialize($request->providerToolNames),
            ];
        } catch (ToolAuthorizationDeniedException $exception) {
            $this->reportRuntimeFailure(
                request: $request,
                throwable: $exception,
                projectedMessageCount: $projectedConversation->projectedMessageCount(),
                packageConversationId: $this->packageConversationId($request, $projectedConversation),
            );

            throw $exception;
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $projectedConversation->projectedMessageCount(),
                packageConversationId: $this->packageConversationId($request, $projectedConversation),
            );
        }

        $telemetryContext = RuntimeTelemetryContext::fromRequest($request, $projectedConversation);
        $instructions = $this->instructionsAsString($request, $projectedConversation->systemInstructions);

        try {
            $agent = $this->buildAgent(
                request: $request,
                telemetryContext: $telemetryContext,
                instructions: $instructions,
                messages: $projectedConversation->messages,
                materializedTools: $materializedTools,
            );
        } catch (SchemaResolutionException $exception) {
            $this->reportRuntimeFailure(
                request: $request,
                throwable: $exception,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );

            throw $exception;
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );
        }

        try {
            $effectiveAttachments = $this->effectivePromptAttachments($request, $projectedConversation);

            $response = $agent->prompt(
                prompt: $request->prompt,
                attachments: $effectiveAttachments,
                provider: $request->provider,
                model: $request->model,
                timeout: $request->timeout,
            );
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
                failureCategory: FailureCategory::ProviderFailure->value,
            );
        }

        $promptTokens = $response->usage->promptTokens ?? 0;
        $completionTokens = $response->usage->completionTokens ?? 0;
        $totalTokens = $promptTokens + $completionTokens;

        try {
            $this->runtimeBudgetEnforcer->assertResponseWithinBudgets(
                runId: $request->runId,
                totalTokens: $totalTokens,
                toolCallCount: $response->toolCalls->count(),
                estimatedCostUsd: $estimatedCostUsd,
            );
        } catch (RuntimeBudgetExceededException $exception) {
            $this->reportRuntimeFailure(
                request: $request,
                throwable: $exception,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );

            throw $exception;
        }

        try {
            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $request,
                response: $response,
                userTurnAttachments: $this->effectivePromptAttachments($request, $projectedConversation),
            );
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );
        }

        $usage = [
          'prompt_tokens' => $promptTokens,
          'completion_tokens' => $completionTokens,
          'total_tokens' => $promptTokens + $completionTokens,
        ];

        return new ExecutionResult(
            runId: $request->runId,
            output: $response->text,
            provider: $response->meta->provider,
            model: $response->meta->model,
            usage: $usage,
            metadata: [
            'invocation_id' => $response->invocationId,
            'conversation_id' => $response->conversationId,
            'message_count' => $response->messages->count(),
            'tool_call_count' => $response->toolCalls->count(),
            'tool_result_count' => $response->toolResults->count(),
            'step_count' => $response->steps->count(),
            'requested_tool_names' => $request->toolNames,
            'requested_provider_tool_names' => $request->providerToolNames,
            'materialized_tool_count' => count($materializedTools),
            'projected_message_count' => $projectedConversation->projectedMessageCount(),
            'package_conversation_id' => $conversation?->id->toString(),
            'package_conversation_message_count' => $conversation?->messageCount(),
            'estimated_cost_usd' => $estimatedCostUsd,
            'attachment_replay_merge' => $projectedConversation->attachmentReplayMerge,
            'attachment_replay_prior_included' => $projectedConversation->priorAttachmentReplayCount,
            'attachment_replay_prior_excluded' => $projectedConversation->priorAttachmentExcludedCount,
          ],
            structuredOutput: StructuredAgentResponseMapper::mapStructuredPayload($response),
        );
    }

    /**
     * @return Generator<int, StreamChunk|StreamComplete|StreamFailure>
     */
    public function executeStream(ExecutionRequest $request): Generator
    {
        if ($request->schema !== null) {
            throw new InvalidArgumentException(
                'Streaming execution does not support structured output requests; omit ExecutionRequest::$schema or use execute().',
            );
        }

        $broadcastChannel = $this->resolveStreamingBroadcastChannel($request);

        try {
            $estimatedCostUsd = $this->estimatedCostUsd($request);
        } catch (RuntimeBudgetExceededException $exception) {
            $this->reportStreamFailure($request, $exception, 0, null, $broadcastChannel);
            yield $this->streamFailureFromThrowable($request, $exception);

            return;
        }

        try {
            $projectedConversation = $this->runtimeConversationMemoryBridge->project($request);
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure($request, $throwable, 0, null, $broadcastChannel);
            yield $this->streamFailureFromThrowable($request, $wrapped);

            return;
        }

        try {
            $materializedTools = [
                ...$this->toolMaterializer->materialize($request->toolNames),
                ...$this->providerToolMaterializer->materialize($request->providerToolNames),
            ];
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure(
                $request,
                $throwable,
                $projectedConversation->projectedMessageCount(),
                $this->packageConversationId($request, $projectedConversation),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $wrapped);

            return;
        }

        $telemetryContext = RuntimeTelemetryContext::fromRequest($request, $projectedConversation);
        $instructions = $this->instructionsAsString($request, $projectedConversation->systemInstructions);

        try {
            $agent = $this->buildAgent(
                request: $request,
                telemetryContext: $telemetryContext,
                instructions: $instructions,
                messages: $projectedConversation->messages,
                materializedTools: $materializedTools,
            );
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure(
                $request,
                $throwable,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $wrapped);

            return;
        }

        $stream = $agent->stream(
            prompt: $request->prompt,
            attachments: $this->effectivePromptAttachments($request, $projectedConversation),
            provider: $request->provider,
            model: $request->model,
            timeout: $request->timeout,
        );

        $sequence = 0;
        $terminalEmitted = false;

        try {
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield new StreamChunk(
                        runId: $request->runId,
                        sequence: $sequence,
                        type: 'text_delta',
                        textDelta: $event->delta,
                        metadata: [
                            'message_id' => $event->messageId,
                        ],
                    );

                    $this->dispatchStreamChunkObserved(
                        $request,
                        $sequence,
                        'text_delta',
                        $event->delta,
                        $event->messageId,
                        $broadcastChannel,
                    );
                    $sequence++;

                    continue;
                }

                if ($event instanceof StreamError) {
                    $exception = RuntimeExecutionException::forRequest(
                        runId: $request->runId,
                        previous: new RuntimeException($event->message),
                        failureCategory: FailureCategory::ProviderFailure->value,
                    );
                    $this->reportStreamFailure(
                        $request,
                        $exception,
                        $telemetryContext->projectedMessageCount,
                        $telemetryContext->packageConversationId?->toString(),
                        $broadcastChannel,
                    );
                    yield new StreamFailure(
                        runId: $request->runId,
                        failureCategory: FailureCategory::ProviderFailure->value,
                        exceptionClass: StreamError::class,
                        exceptionMessage: $event->message,
                    );
                    $terminalEmitted = true;
                    break;
                }
            }
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure(
                $request,
                $throwable,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $wrapped);
            $terminalEmitted = true;
        }

        if ($terminalEmitted) {
            return;
        }

        $text = $stream->text ?? '';
        $usage = $stream->usage;
        $promptTokens = $usage->promptTokens ?? 0;
        $completionTokens = $usage->completionTokens ?? 0;
        $totalTokens = $promptTokens + $completionTokens;

        $meta = $this->resolveStreamMeta($stream->events);

        if (!$meta instanceof Meta) {
            $exception = RuntimeExecutionException::forRequest(
                runId: $request->runId,
                previous: new RuntimeException('Stream completed without provider metadata.'),
                failureCategory: FailureCategory::ProviderFailure->value,
            );
            $this->reportStreamFailure(
                $request,
                $exception,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $exception);

            return;
        }

        $streamedResponse = new StreamedAgentResponse(
            $stream->invocationId,
            $stream->events,
            $meta,
        );

        try {
            $this->runtimeBudgetEnforcer->assertResponseWithinBudgets(
                runId: $request->runId,
                totalTokens: $totalTokens,
                toolCallCount: $streamedResponse->toolCalls->count(),
                estimatedCostUsd: $estimatedCostUsd,
            );
        } catch (RuntimeBudgetExceededException $exception) {
            $this->reportStreamFailure(
                $request,
                $exception,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $exception);

            return;
        }

        try {
            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $request,
                response: $streamedResponse,
                userTurnAttachments: $this->effectivePromptAttachments($request, $projectedConversation),
            );
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure(
                $request,
                $throwable,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($request, $wrapped);

            return;
        }

        $usageArray = [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];

        $providerLabel = $meta->provider ?? 'unknown';
        $modelLabel = $meta->model ?? 'unknown';

        $this->dispatchStreamCompleted(
            request: $request,
            invocationId: $stream->invocationId,
            provider: $providerLabel,
            model: $modelLabel,
            projectedMessageCount: $telemetryContext->projectedMessageCount,
            packageConversationId: $conversation?->id->toString() ?? $telemetryContext->packageConversationId?->toString(),
            usage: $usageArray,
            outputLength: strlen($text),
            broadcastChannel: $broadcastChannel,
        );

        yield new StreamComplete(
            runId: $request->runId,
            output: $text,
            provider: $providerLabel,
            model: $modelLabel,
            usage: $usageArray,
            metadata: [
                'invocation_id' => $stream->invocationId,
                'requested_tool_names' => $request->toolNames,
                'requested_provider_tool_names' => $request->providerToolNames,
                'materialized_tool_count' => count($materializedTools),
                'projected_message_count' => $projectedConversation->projectedMessageCount(),
                'package_conversation_id' => $conversation?->id->toString(),
                'package_conversation_message_count' => $conversation?->messageCount(),
                'estimated_cost_usd' => $estimatedCostUsd,
            ],
        );
    }

    /**
     * @param iterable<mixed> $messages
     * @param iterable<mixed> $materializedTools
     */
    private function buildAgent(
        ExecutionRequest $request,
        RuntimeTelemetryContext $telemetryContext,
        string $instructions,
        iterable $messages,
        iterable $materializedTools,
    ): AnonymousAgent {
        if ($request->schema === null) {
            return new RuntimeTelemetryAgent(
                telemetryContext: $telemetryContext,
                instructions: $instructions,
                messages: $messages,
                tools: $materializedTools,
                generationOptions: $request->generationOptions,
            );
        }

        $schemaClosure = $this->normalizeSchema($request->schema);

        return new StructuredRuntimeTelemetryAgent(
            telemetryContext: $telemetryContext,
            instructions: $instructions,
            messages: $messages,
            tools: $materializedTools,
            schema: $schemaClosure,
            generationOptions: $request->generationOptions,
        );
    }

    /**
     * Normalize any of {Closure, ObjectSchema, class-string<HasStructuredOutput>}
     * into the Closure that StructuredAnonymousAgent consumes.
     */
    private function normalizeSchema(Closure|ObjectSchema|string $schema): Closure
    {
        if ($schema instanceof Closure) {
            return $schema;
        }

        if ($schema instanceof ObjectSchema) {
            return fn (JsonSchema $js): array => $schema->toSchema();
        }

        // class-string<HasStructuredOutput>
        if (!class_exists($schema)) {
            throw SchemaResolutionException::forMissingClass($schema);
        }

        $instance = $this->container->make($schema);

        if (!$instance instanceof HasStructuredOutput) {
            throw SchemaResolutionException::forContractMismatch($schema);
        }

        return fn (JsonSchema $js): array => $instance->schema($js);
    }

    /**
     * @return list<File>
     */
    private function effectivePromptAttachments(ExecutionRequest $request, ProjectedConversationContext $projected): array
    {
        $prior = $projected->priorReplayAttachments;
        $mode = $projected->attachmentReplayRequestMode ?? 'none';

        if ($prior === [] || $mode === 'none') {
            return $request->attachments;
        }

        if ($mode === 'replay_only') {
            return $prior;
        }

        if ($mode === 'merge') {
            return [...$prior, ...$request->attachments];
        }

        return $request->attachments;
    }

    private function estimatedCostUsd(ExecutionRequest $request): ?float
    {
        $value = $request->metadata['cost_usd'] ?? $request->metadata['estimated_cost_usd'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw RuntimeBudgetExceededException::forInvalidEstimatedCostType($request->runId, get_debug_type($value));
        }

        if ($value < 0) {
            throw RuntimeBudgetExceededException::forInvalidEstimatedCostValue($request->runId, (float)$value);
        }

        return (float)$value;
    }

    private function reportRuntimeFailure(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
    ): void {
        if (!$this->events instanceof Dispatcher) {
            return;
        }

        try {
            $this->events->dispatch(
                RuntimeExecutionFailed::fromRequest(
                    request: $request,
                    throwable: $throwable,
                    projectedMessageCount: $projectedMessageCount,
                    packageConversationId: $packageConversationId,
                    redactor: $this->redactor,
                ),
            );
        } catch (Throwable) {
            // Intentionally suppressed – preserving the original exception is paramount.
        }
    }

    private function wrapAndReportRuntimeFailure(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
        string $failureCategory = FailureCategory::ExecutionFailed->value,
    ): RuntimeExecutionException {
        $exception = RuntimeExecutionException::forRequest(
            runId: $request->runId,
            previous: $throwable,
            failureCategory: $failureCategory,
        );

        $this->reportRuntimeFailure(
            request: $request,
            throwable: $exception,
            projectedMessageCount: $projectedMessageCount,
            packageConversationId: $packageConversationId,
        );

        return $exception;
    }

    private function packageConversationId(
        ExecutionRequest $request,
        ProjectedConversationContext $projectedConversation,
    ): ?string {
        if ($projectedConversation->context?->conversationId instanceof ConversationId) {
            return $projectedConversation->context->conversationId->toString();
        }

        return $request->conversationId?->toString();
    }

    /**
     * @param list<string> $projectedSystemInstructions
     */
    private function instructionsAsString(ExecutionRequest $request, array $projectedSystemInstructions = []): string
    {
        /** @var list<string> $instructions */
        $instructions = array_values(
            array_filter(
                array_merge($projectedSystemInstructions, $request->instructions),
                static fn (string $instruction): bool => $instruction !== '',
            ),
        );

        if ($instructions === []) {
            return 'You are the Laravel AI Agent Kit runtime bridge.';
        }

        return implode("\n\n", $instructions);
    }

    /**
     * @param Collection<int, mixed> $events
     */
    private function resolveStreamMeta(Collection $events): ?Meta
    {
        $start = $events->first(static fn (mixed $event): bool => $event instanceof StreamStart);

        if ($start instanceof StreamStart) {
            return new Meta($start->provider, $start->model);
        }

        return null;
    }

    private function resolveStreamingBroadcastChannel(ExecutionRequest $request): ?string
    {
        $fromRequest = $request->metadata['streaming_broadcast_channel'] ?? null;

        if (is_string($fromRequest) && $fromRequest !== '') {
            return $fromRequest;
        }

        /** @var mixed $fromConfig */
        $fromConfig = $this->container->make('config')->get('ai-agent-kit.runtime.streaming.broadcast_channel');

        return is_string($fromConfig) && $fromConfig !== '' ? $fromConfig : null;
    }

    private function dispatchStreamChunkObserved(
        ExecutionRequest $request,
        int $sequence,
        string $type,
        string $delta,
        string $messageId,
        ?string $broadcastChannel,
    ): void {
        if (!$this->events instanceof Dispatcher) {
            return;
        }

        try {
            $this->events->dispatch(
                new RuntimeStreamChunkEmitted(
                    runId: $request->runId,
                    sequence: $sequence,
                    type: $type,
                    deltaLength: strlen($delta),
                    messageId: $messageId,
                    broadcastChannel: $broadcastChannel,
                ),
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, int> $usage
     */
    private function dispatchStreamCompleted(
        ExecutionRequest $request,
        string $invocationId,
        string $provider,
        string $model,
        int $projectedMessageCount,
        ?string $packageConversationId,
        array $usage,
        int $outputLength,
        ?string $broadcastChannel,
    ): void {
        if (!$this->events instanceof Dispatcher) {
            return;
        }

        try {
            $this->events->dispatch(
                new RuntimeStreamCompleted(
                    runId: $request->runId,
                    invocationId: $invocationId,
                    provider: $provider,
                    model: $model,
                    requestedToolNames: $request->toolNames,
                    metadataKeys: RequestObservabilityKeys::metadataKeys($request, $this->redactor),
                    packageConversationId: $packageConversationId,
                    projectedMessageCount: $projectedMessageCount,
                    promptTokens: $usage['prompt_tokens'] ?? 0,
                    completionTokens: $usage['completion_tokens'] ?? 0,
                    totalTokens: $usage['total_tokens'] ?? 0,
                    outputLength: $outputLength,
                    broadcastChannel: $broadcastChannel,
                ),
            );
        } catch (Throwable) {
        }
    }

    private function reportStreamFailure(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
        ?string $broadcastChannel = null,
    ): void {
        if (!$this->events instanceof Dispatcher) {
            return;
        }

        try {
            $this->events->dispatch(
                RuntimeStreamFailed::fromRequest(
                    request: $request,
                    throwable: $throwable,
                    projectedMessageCount: $projectedMessageCount,
                    packageConversationId: $packageConversationId,
                    redactor: $this->redactor,
                    broadcastChannel: $broadcastChannel,
                ),
            );
        } catch (Throwable) {
        }
    }

    private function wrapStreamFailure(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
        ?string $broadcastChannel = null,
        string $failureCategory = FailureCategory::ExecutionFailed->value,
    ): RuntimeExecutionException {
        $exception = RuntimeExecutionException::forRequest(
            runId: $request->runId,
            previous: $throwable,
            failureCategory: $failureCategory,
        );

        $this->reportStreamFailure(
            request: $request,
            throwable: $exception,
            projectedMessageCount: $projectedMessageCount,
            packageConversationId: $packageConversationId,
            broadcastChannel: $broadcastChannel,
        );

        return $exception;
    }

    private function streamFailureFromThrowable(ExecutionRequest $request, Throwable $throwable): StreamFailure
    {
        $category = $throwable instanceof RuntimeExecutionException
            ? $throwable->failureCategory()
            : FailureCategoryResolver::forThrowable($throwable);

        $message = $throwable->getMessage();
        if ($throwable instanceof RuntimeExecutionException && $throwable->getPrevious() instanceof Throwable) {
            $message = $throwable->getPrevious()->getMessage();
        }

        return new StreamFailure(
            runId: $request->runId,
            failureCategory: $category,
            exceptionClass: $throwable::class,
            exceptionMessage: $message !== '' ? $message : null,
        );
    }
}
