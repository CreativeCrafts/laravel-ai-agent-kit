<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Throwable;

final readonly class SdkAiRuntime implements AiRuntime
{
    public function __construct(
        private SdkToolMaterializer $toolMaterializer,
        private RuntimeConversationMemoryBridge $runtimeConversationMemoryBridge,
        private RuntimeBudgetEnforcer $runtimeBudgetEnforcer,
    ) {
    }

    public function execute(ExecutionRequest $request): ExecutionResult
    {
        $promptTokens = 0;
        $completionTokens = 0;
        $estimatedCostUsd = $this->estimatedCostUsd($request);

        try {
            $projectedConversation = $this->runtimeConversationMemoryBridge->project($request);
            $materializedTools = $this->toolMaterializer->materialize($request->toolNames);
            $telemetryContext = RuntimeTelemetryContext::fromRequest($request, $projectedConversation);

            $agent = new RuntimeTelemetryAgent(
                telemetryContext: $telemetryContext,
                instructions: $this->instructionsAsString($request, $projectedConversation->systemInstructions),
                messages: $projectedConversation->messages,
                tools: $materializedTools,
            );

            $response = $agent->prompt(
                prompt: $request->prompt,
                provider: $request->provider,
                model: $request->model,
                timeout: $request->timeout,
            );

            $promptTokens = $response->usage->promptTokens ?? 0;
            $completionTokens = $response->usage->completionTokens ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            $this->runtimeBudgetEnforcer->assertResponseWithinBudgets(
                runId: $request->runId,
                totalTokens: $totalTokens,
                toolCallCount: $response->toolCalls->count(),
                estimatedCostUsd: $estimatedCostUsd,
            );

            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $request,
                response: $response,
            );
        } catch (RuntimeBudgetExceededException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw RuntimeExecutionException::forRequest($request->runId, $throwable);
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
            'materialized_tool_count' => count($materializedTools),
            'projected_message_count' => $projectedConversation->projectedMessageCount(),
            'package_conversation_id' => $conversation?->id->toString(),
            'package_conversation_message_count' => $conversation?->messageCount(),
            'estimated_cost_usd' => $estimatedCostUsd,
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
