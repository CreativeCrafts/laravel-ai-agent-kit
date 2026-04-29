<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\SchemaResolutionException;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use Throwable;

final readonly class SdkAiRuntime implements AiRuntime
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
            $response = $agent->prompt(
                prompt: $request->prompt,
                attachments: $request->attachments,
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
          ],
            structuredOutput: StructuredAgentResponseMapper::mapStructuredPayload($response),
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
}
