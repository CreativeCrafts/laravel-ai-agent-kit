<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
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
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Generator;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Files\File;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;
use Throwable;

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

    /**
     * @throws BindingResolutionException
     */
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
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );
        }

        $effectiveAttachments = $this->effectivePromptAttachments($request, $projectedConversation);
        $attemptedProviders = [];
        $attemptRequest = $this->requestForInitialProviderAttempt($request);
        $lastProviderThrowable = null;

        while (true) {
            $attemptedProviders[] = $attemptRequest->provider ?? 'default';

            try {
                $response = $agent->prompt(
                    prompt: $attemptRequest->prompt,
                    attachments: $effectiveAttachments,
                    provider: $attemptRequest->provider,
                    model: $attemptRequest->model,
                    timeout: $attemptRequest->timeout,
                );

                $this->recordProviderSuccess($attemptRequest->provider);
                break;
            } catch (Throwable $throwable) {
                $lastProviderThrowable = $throwable;
                $this->recordProviderFailure($attemptRequest->provider);

                $nextProvider = $this->nextFailoverProvider($attemptRequest->provider);

                if (!$nextProvider instanceof ProviderDefinition) {
                    throw $this->wrapAndReportRuntimeFailure(
                        request: $attemptRequest->withMetadata([
                        'runtime_provider_attempts' => $attemptedProviders,
                        'runtime_failover_exhausted' => true,
                      ]),
                        throwable: $throwable,
                        projectedMessageCount: $telemetryContext->projectedMessageCount,
                        packageConversationId: $telemetryContext->packageConversationId?->toString(),
                        failureCategory: FailureCategory::ProviderFailure->value,
                    );
                }

                $attemptRequest = $this->requestForProviderDefinition($request, $nextProvider);
            }
        }

        unset($lastProviderThrowable);

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
                request: $attemptRequest,
                throwable: $exception,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );

            throw $exception;
        }

        try {
            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $attemptRequest,
                response: $response,
                userTurnAttachments: $effectiveAttachments,
            );
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $attemptRequest,
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
            'runtime_provider_attempts' => $attemptedProviders,
            'runtime_final_provider' => $attemptRequest->provider,
            'runtime_failover_attempted' => count($attemptedProviders) > 1,
          ],
            structuredOutput: StructuredAgentResponseMapper::mapStructuredPayload($response),
        );
    }

    /**
     * @return Generator<int, StreamChunk|StreamComplete|StreamFailure>
     * @throws BindingResolutionException
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

        $effectiveAttachments = $this->effectivePromptAttachments($request, $projectedConversation);
        $attemptedProviders = [];
        $attemptRequest = $this->requestForInitialProviderAttempt($request);

        while (true) {
            $attemptedProviders[] = $attemptRequest->provider ?? 'default';

            try {
                $stream = $agent->stream(
                    prompt: $attemptRequest->prompt,
                    attachments: $effectiveAttachments,
                    provider: $attemptRequest->provider,
                    model: $attemptRequest->model,
                    timeout: $attemptRequest->timeout,
                );

                break;
            } catch (Throwable $throwable) {
                $this->recordProviderFailure($attemptRequest->provider);
                $nextProvider = $this->nextFailoverProvider($attemptRequest->provider);

                if (!$nextProvider instanceof ProviderDefinition) {
                    $wrapped = $this->wrapStreamFailure(
                        request: $attemptRequest->withMetadata([
                        'runtime_provider_attempts' => $attemptedProviders,
                        'runtime_failover_exhausted' => true,
                      ]),
                        throwable: $throwable,
                        projectedMessageCount: $telemetryContext->projectedMessageCount,
                        packageConversationId: $telemetryContext->packageConversationId?->toString(),
                        broadcastChannel: $broadcastChannel,
                        failureCategory: FailureCategory::ProviderFailure->value,
                    );
                    yield $this->streamFailureFromThrowable($attemptRequest, $wrapped);

                    return;
                }

                $attemptRequest = $this->requestForProviderDefinition($request, $nextProvider);
            }
        }

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
                        $attemptRequest,
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
                    $this->recordProviderFailure($attemptRequest->provider);

                    $exception = RuntimeExecutionException::forRequest(
                        runId: $request->runId,
                        previous: new RuntimeException($event->message),
                        failureCategory: FailureCategory::ProviderFailure->value,
                    );

                    $this->reportStreamFailure(
                        $attemptRequest,
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
            $this->recordProviderFailure($attemptRequest->provider);

            $wrapped = $this->wrapStreamFailure(
                request: $attemptRequest,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
                broadcastChannel: $broadcastChannel,
                failureCategory: FailureCategory::ProviderFailure->value,
            );

            yield $this->streamFailureFromThrowable($attemptRequest, $wrapped);
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
            $this->recordProviderFailure($attemptRequest->provider);

            $exception = RuntimeExecutionException::forRequest(
                runId: $request->runId,
                previous: new RuntimeException('Stream completed without provider metadata.'),
                failureCategory: FailureCategory::ProviderFailure->value,
            );

            $this->reportStreamFailure(
                $attemptRequest,
                $exception,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );

            yield $this->streamFailureFromThrowable($attemptRequest, $exception);

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
                $attemptRequest,
                $exception,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($attemptRequest, $exception);

            return;
        }

        try {
            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $attemptRequest,
                response: $streamedResponse,
                userTurnAttachments: $effectiveAttachments,
            );
        } catch (Throwable $throwable) {
            $wrapped = $this->wrapStreamFailure(
                $attemptRequest,
                $throwable,
                $telemetryContext->projectedMessageCount,
                $telemetryContext->packageConversationId?->toString(),
                $broadcastChannel,
            );
            yield $this->streamFailureFromThrowable($attemptRequest, $wrapped);

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
            request: $attemptRequest,
            invocationId: $stream->invocationId,
            provider: $providerLabel,
            model: $modelLabel,
            projectedMessageCount: $telemetryContext->projectedMessageCount,
            packageConversationId: $conversation?->id->toString() ?? $telemetryContext->packageConversationId?->toString(),
            usage: $usageArray,
            outputLength: strlen($text),
            broadcastChannel: $broadcastChannel,
        );

        $this->recordProviderSuccess($attemptRequest->provider);

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
            'runtime_provider_attempts' => $attemptedProviders,
            'runtime_final_provider' => $attemptRequest->provider,
            'runtime_failover_attempted' => count($attemptedProviders) > 1,
          ],
        );
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
     * @param iterable<mixed> $messages
     * @param iterable<mixed> $materializedTools
     * @throws BindingResolutionException
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
                instructions: $instructions,
                messages: $messages,
                tools: $materializedTools,
                telemetryContext: $telemetryContext,
                generationOptions: $request->generationOptions,
            );
        }

        $schemaClosure = $this->normalizeSchema($request->schema);

        return new StructuredRuntimeTelemetryAgent(
            instructions: $instructions,
            messages: $messages,
            tools: $materializedTools,
            schema: $schemaClosure,
            telemetryContext: $telemetryContext,
            generationOptions: $request->generationOptions,
        );
    }

    /**
     * Normalize any of {Closure, ObjectSchema, class-string<HasStructuredOutput>}
     * into the Closure that StructuredAnonymousAgent consumes.
     *
     * @throws BindingResolutionException
     */
    private function normalizeSchema(Closure|ObjectSchema|string $schema): Closure
    {
        if ($schema instanceof Closure) {
            return $schema;
        }

        if ($schema instanceof ObjectSchema) {
            return static fn (JsonSchema $js): array => $schema->toSchema();
        }

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

    /**
     * @throws BindingResolutionException
     */
    private function requestForInitialProviderAttempt(ExecutionRequest $request): ExecutionRequest
    {
        if ($request->provider !== null) {
            return $this->requestForProviderName($request, $request->provider);
        }

        /** @var ProviderSelector $selector */
        $selector = $this->container->make(ProviderSelector::class);

        return $this->requestForProviderDefinition($request, $selector->selectDefault());
    }

    /**
     * @throws BindingResolutionException
     */
    private function requestForProviderName(ExecutionRequest $request, string $providerName): ExecutionRequest
    {
        $definition = $this->providerDefinitionByName($providerName);

        if ($definition instanceof ProviderDefinition) {
            return $this->requestForProviderDefinition($request, $definition);
        }

        return $this->cloneRequestWithProvider(
            request: $request,
            provider: $providerName,
            model: $request->model,
        );
    }

    /**
     * @throws BindingResolutionException
     */
    private function providerDefinitionByName(string $providerName): ?ProviderDefinition
    {
        foreach ($this->failoverProviderSelector()->ordered() as $provider) {
            if ($provider->name === $providerName) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @throws BindingResolutionException
     */
    private function failoverProviderSelector(): FailoverProviderSelector
    {
        /** @var FailoverProviderSelector $selector */
        $selector = $this->container->make(FailoverProviderSelector::class);

        return $selector;
    }

    private function requestForProviderDefinition(ExecutionRequest $request, ProviderDefinition $provider): ExecutionRequest
    {
        $configuredModel = $provider->options['model'] ?? null;

        return $this->cloneRequestWithProvider(
            request: $request,
            provider: $provider->name,
            model: $request->model ?? (is_string($configuredModel) && $configuredModel !== '' ? $configuredModel : null),
        );
    }

    private function cloneRequestWithProvider(ExecutionRequest $request, ?string $provider, ?string $model): ExecutionRequest
    {
        return new ExecutionRequest(
            runId: $request->runId,
            prompt: $request->prompt,
            instructions: $request->instructions,
            provider: $provider,
            model: $model,
            toolNames: $request->toolNames,
            input: $request->input,
            metadata: $request->metadata,
            timeout: $request->timeout,
            conversationId: $request->conversationId,
            storeConversation: $request->storeConversation,
            continueConversation: $request->continueConversation,
            generationOptions: $request->generationOptions,
            schema: $request->schema,
            attachments: $request->attachments,
            providerToolNames: $request->providerToolNames,
        );
    }

    private function recordProviderSuccess(?string $providerName): void
    {
        if ($providerName === null || $providerName === '') {
            return;
        }

        $this->circuitBreakerManager()?->for('providers.' . $providerName)->recordSuccess();
    }

    private function circuitBreakerManager(): ?CircuitBreakerManager
    {
        try {
            /** @var CircuitBreakerManager $manager */
            $manager = $this->container->make(CircuitBreakerManager::class);

            return $manager;
        } catch (Throwable) {
            return null;
        }
    }

    private function recordProviderFailure(?string $providerName): void
    {
        if ($providerName === null || $providerName === '') {
            return;
        }

        $this->circuitBreakerManager()?->for('providers.' . $providerName)->recordFailure();
    }

    private function nextFailoverProvider(?string $currentProviderName): ?ProviderDefinition
    {
        if ($currentProviderName === null || $currentProviderName === '') {
            return null;
        }

        try {
            return $this->failoverProviderSelector()->nextAfter($currentProviderName);
        } catch (ProviderNotInFailoverOrderException) {
            return null;
        }
    }

    /**
     * @throws BindingResolutionException
     */
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
     * @param Collection<int, StreamEvent> $events
     */
    private function resolveStreamMeta(Collection $events): ?Meta
    {
        $start = $events->first(static fn (StreamEvent $event): bool => $event instanceof StreamStart);

        if ($start instanceof StreamStart) {
            return new Meta($start->provider, $start->model);
        }

        return null;
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
}
