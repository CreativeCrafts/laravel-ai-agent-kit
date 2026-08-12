<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ResolvedProviderTarget;
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
use Laravel\Ai\Enums\Lab;
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
        private ProviderTargetResolver $providerTargetResolver,
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
        $effectiveAttachments = $this->effectivePromptAttachments($request, $projectedConversation);
        $attemptedProfiles = [];
        $attemptedSdkProviders = [];
        $attempt = $this->providerTargetResolver->resolve($request->provider, $request->model);
        $attemptRequest = $request->withProviderIdentity($attempt->policyIdentity(), $attempt->model);
        $lastProviderThrowable = null;

        try {
            $schemaClosure = $request->schema === null ? null : $this->normalizeSchema($request->schema);
        } catch (Throwable $throwable) {
            throw $this->wrapAndReportRuntimeFailure(
                request: $request,
                throwable: $throwable,
                projectedMessageCount: $telemetryContext->projectedMessageCount,
                packageConversationId: $telemetryContext->packageConversationId?->toString(),
            );
        }

        while (true) {
            $attemptRequest = $request->withProviderIdentity($attempt->policyIdentity(), $attempt->model);
            $attemptedProfiles[] = $attempt->policyIdentity();
            $attemptedSdkProviders[] = $attempt->sdkProviderName ?? 'default';

            try {
                $agent = $this->buildAgent(
                    request: $request,
                    telemetryContext: $telemetryContext,
                    instructions: $instructions,
                    messages: $projectedConversation->messages,
                    materializedTools: $materializedTools,
                    generationOptions: $this->generationOptionsForAttempt($request, $attempt),
                    schemaClosure: $schemaClosure,
                );

                $response = $agent->prompt(
                    prompt: $attemptRequest->prompt,
                    attachments: $effectiveAttachments,
                    provider: $attempt->sdkProviderName,
                    model: $attempt->model,
                    timeout: $attemptRequest->timeout,
                );

                $this->recordProviderSuccess($attempt->policyIdentity());
                break;
            } catch (Throwable $throwable) {
                $lastProviderThrowable = $throwable;
                $this->recordProviderFailure($attempt->policyIdentity());

                $nextProvider = $this->nextFailoverProvider($attempt->policyIdentity());

                if (!$nextProvider instanceof ProviderDefinition) {
                    throw $this->wrapAndReportRuntimeFailure(
                        request: $attemptRequest->withMetadata([
                        'runtime_provider_attempts' => $attemptedProfiles,
                        'runtime_sdk_provider_attempts' => $attemptedSdkProviders,
                        'runtime_failover_exhausted' => true,
                      ]),
                        throwable: $throwable,
                        projectedMessageCount: $telemetryContext->projectedMessageCount,
                        packageConversationId: $telemetryContext->packageConversationId?->toString(),
                        failureCategory: FailureCategory::ProviderFailure->value,
                    );
                }

                $attempt = $this->providerTargetResolver->fromDefinition($nextProvider, $request->model);
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
            'runtime_provider_attempts' => $attemptedProfiles,
            'runtime_sdk_provider_attempts' => $attemptedSdkProviders,
            'runtime_final_provider' => $attempt->policyIdentity(),
            'runtime_final_sdk_provider' => $attempt->sdkProviderName,
            'runtime_failover_attempted' => count($attemptedProfiles) > 1,
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
        $effectiveAttachments = $this->effectivePromptAttachments($request, $projectedConversation);
        $attemptedProfiles = [];
        $attemptedSdkProviders = [];
        $attempt = $this->providerTargetResolver->resolve($request->provider, $request->model);
        $attemptRequest = $request->withProviderIdentity($attempt->policyIdentity(), $attempt->model);

        while (true) {
            $attemptRequest = $request->withProviderIdentity($attempt->policyIdentity(), $attempt->model);
            $attemptedProfiles[] = $attempt->policyIdentity();
            $attemptedSdkProviders[] = $attempt->sdkProviderName ?? 'default';

            try {
                $agent = $this->buildAgent(
                    request: $request,
                    telemetryContext: $telemetryContext,
                    instructions: $instructions,
                    messages: $projectedConversation->messages,
                    materializedTools: $materializedTools,
                    generationOptions: $this->generationOptionsForAttempt($request, $attempt),
                    schemaClosure: null,
                );

                $stream = $agent->stream(
                    prompt: $attemptRequest->prompt,
                    attachments: $effectiveAttachments,
                    provider: $attempt->sdkProviderName,
                    model: $attempt->model,
                    timeout: $attemptRequest->timeout,
                );

                break;
            } catch (Throwable $throwable) {
                $this->recordProviderFailure($attempt->policyIdentity());
                $nextProvider = $this->nextFailoverProvider($attempt->policyIdentity());

                if (!$nextProvider instanceof ProviderDefinition) {
                    $wrapped = $this->wrapStreamFailure(
                        request: $attemptRequest->withMetadata([
                        'runtime_provider_attempts' => $attemptedProfiles,
                        'runtime_sdk_provider_attempts' => $attemptedSdkProviders,
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

                $attempt = $this->providerTargetResolver->fromDefinition($nextProvider, $request->model);
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
                    $this->recordProviderFailure($attempt->policyIdentity());

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
            $this->recordProviderFailure($attempt->policyIdentity());

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
            $this->recordProviderFailure($attempt->policyIdentity());

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

        $this->recordProviderSuccess($attempt->policyIdentity());

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
            'runtime_provider_attempts' => $attemptedProfiles,
            'runtime_sdk_provider_attempts' => $attemptedSdkProviders,
            'runtime_final_provider' => $attempt->policyIdentity(),
            'runtime_final_sdk_provider' => $attempt->sdkProviderName,
            'runtime_failover_attempted' => count($attemptedProfiles) > 1,
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
            return $this->configuredDefaultInstructions();
        }

        return implode("\n\n", $instructions);
    }

    private function configuredDefaultInstructions(): string
    {
        try {
            /** @var mixed $configured */
            $configured = $this->container->make('config')->get('ai-agent-kit.runtime.default_instructions', []);
        } catch (Throwable) {
            return '';
        }

        if (is_string($configured)) {
            return $configured;
        }

        if (!is_array($configured)) {
            return '';
        }

        $parts = [];

        foreach ($configured as $instruction) {
            if (!is_string($instruction) || $instruction === '') {
                continue;
            }

            $parts[] = $instruction;
        }

        return implode("\n\n", $parts);
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
        ?GenerationOptions $generationOptions,
        ?Closure $schemaClosure,
    ): AnonymousAgent {
        if (!$schemaClosure instanceof Closure) {
            return new RuntimeTelemetryAgent(
                instructions: $instructions,
                messages: $messages,
                tools: $materializedTools,
                telemetryContext: $telemetryContext,
                generationOptions: $generationOptions,
            );
        }

        if ($request->strictStructuredOutput) {
            return new StrictStructuredRuntimeTelemetryAgent(
                instructions: $instructions,
                messages: $messages,
                tools: $materializedTools,
                schema: $schemaClosure,
                telemetryContext: $telemetryContext,
                generationOptions: $generationOptions,
            );
        }

        return new StructuredRuntimeTelemetryAgent(
            instructions: $instructions,
            messages: $messages,
            tools: $materializedTools,
            schema: $schemaClosure,
            telemetryContext: $telemetryContext,
            generationOptions: $generationOptions,
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
     * Merge profile-default provider options with request overrides for one attempt.
     */
    private function generationOptionsForAttempt(ExecutionRequest $request, ResolvedProviderTarget $attempt): GenerationOptions
    {
        $base = $request->generationOptions ?? new GenerationOptions();

        return $base->forProviderAttempt(
            sdkProviderName: $attempt->sdkProviderName ?? '',
            driver: $attempt->driver ?? '',
            profileOptions: $attempt->providerOptions,
            additionalScopeKeys: $this->providerOptionScopeKeys(),
        );
    }

    /**
     * @return list<string>
     */
    private function providerOptionScopeKeys(): array
    {
        $keys = $this->providerTargetResolver->knownProviderScopeKeys();

        foreach (Lab::cases() as $lab) {
            if (in_array($lab->value, $keys, true)) {
                continue;
            }

            $keys[] = $lab->value;
        }

        return $keys;
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
