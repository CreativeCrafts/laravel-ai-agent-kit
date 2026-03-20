<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use Laravel\Ai\AnonymousAgent;
use Throwable;

final class SdkAiRuntime implements AiRuntime
{
    public function execute(ExecutionRequest $request): ExecutionResult
    {
        $agent = new AnonymousAgent(
            instructions: $this->instructionsAsString($request),
            messages: [],
            tools: [],
        );

        try {
            $response = $agent->prompt(
                prompt: $request->prompt,
                provider: $request->provider,
                model: $request->model,
                timeout: $request->timeout,
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
          ],
        );
    }

    private function instructionsAsString(ExecutionRequest $request): string
    {
        $instructions = array_values(
            array_filter(
                $request->instructions,
                static fn (string $instruction): bool => $instruction !== '',
            ),
        );

        if ($instructions === []) {
            return 'You are the Laravel AI Agent Kit runtime bridge.';
        }

        return implode("\n\n", $instructions);
    }
}
