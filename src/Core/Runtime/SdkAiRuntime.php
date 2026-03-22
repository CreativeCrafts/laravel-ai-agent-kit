<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Laravel\Ai\AnonymousAgent;
use Throwable;

final readonly class SdkAiRuntime implements AiRuntime
{
    public function __construct(
        private SdkToolMaterializer $toolMaterializer,
        private RuntimeConversationMemoryBridge $runtimeConversationMemoryBridge,
    ) {
    }

    public function execute(ExecutionRequest $request): ExecutionResult
    {
        try {
            $projectedConversation = $this->runtimeConversationMemoryBridge->project($request);
            $materializedTools = $this->toolMaterializer->materialize($request->toolNames);

            $agent = new AnonymousAgent(
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

            $conversation = $this->runtimeConversationMemoryBridge->reconcile(
                projected: $projectedConversation,
                request: $request,
                response: $response,
            );
        } catch (Throwable $throwable) {
            throw RuntimeExecutionException::forRequest($request->runId, $throwable);
        }

        $promptTokens = $response->usage->promptTokens;
        $completionTokens = $response->usage->completionTokens;

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
          ],
        );
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
