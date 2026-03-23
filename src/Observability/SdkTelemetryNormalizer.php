<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeToolInvocationCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeToolInvocationStarted;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;

final readonly class SdkTelemetryNormalizer
{
    public function __construct(
        private Dispatcher $events,
        private Redactor $redactor,
    ) {
    }

    public function handlePromptingAgent(PromptingAgent $event): void
    {
        $agent = $event->prompt->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return;
        }

        $telemetry = $agent->telemetryContext;

        $this->events->dispatch(
            new RuntimeExecutionStarted(
                runId: $telemetry->runId,
                invocationId: $event->invocationId,
                provider: $this->resolveProviderName($event->prompt->provider()),
                model: $event->prompt->model,
                requestedToolNames: $telemetry->requestedToolNames,
                inputKeys: $this->redactor->redactKeys(array_fill_keys($telemetry->inputKeys, true)),
                metadataKeys: $this->redactor->redactKeys(array_fill_keys($telemetry->metadataKeys, true)),
                packageConversationId: $telemetry->packageConversationId?->toString(),
                storeConversation: $telemetry->storeConversation,
                continueConversation: $telemetry->continueConversation,
                projectedMessageCount: $telemetry->projectedMessageCount,
                promptLength: strlen($event->prompt->prompt),
                attachmentCount: $event->prompt->attachments->count(),
                timeout: $event->prompt->timeout,
            ),
        );
    }

    public function handleAgentPrompted(AgentPrompted $event): void
    {
        $agent = $event->prompt->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return;
        }

        $telemetry = $agent->telemetryContext;
        $response = $event->response;

        $this->events->dispatch(
            new RuntimeExecutionCompleted(
                runId: $telemetry->runId,
                invocationId: $event->invocationId,
                provider: $this->normalizeString($response->meta->provider, 'unknown'),
                model: $this->normalizeString($response->meta->model, 'unknown'),
                requestedToolNames: $telemetry->requestedToolNames,
                packageConversationId: $telemetry->packageConversationId?->toString(),
                sdkConversationId: $response->conversationId,
                projectedMessageCount: $telemetry->projectedMessageCount,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                totalTokens: $response->usage->promptTokens + $response->usage->completionTokens,
                outputLength: strlen($response->text),
                messageCount: $response->messages->count(),
                toolCallCount: $response->toolCalls->count(),
                toolResultCount: $response->toolResults->count(),
                stepCount: $response->steps->count(),
            ),
        );
    }

    public function handleInvokingTool(InvokingTool $event): void
    {
        $agent = $event->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return;
        }

        $this->events->dispatch(
            new RuntimeToolInvocationStarted(
                runId: $agent->telemetryContext->runId,
                invocationId: $event->invocationId,
                toolInvocationId: $event->toolInvocationId,
                toolName: $this->resolveToolName($event->tool),
                argumentKeys: $this->redactor->redactKeys($this->argumentsAsMap($event->arguments)),
                packageConversationId: $agent->telemetryContext->packageConversationId?->toString(),
            ),
        );
    }

    public function handleToolInvoked(ToolInvoked $event): void
    {
        $agent = $event->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return;
        }

        $this->events->dispatch(
            new RuntimeToolInvocationCompleted(
                runId: $agent->telemetryContext->runId,
                invocationId: $event->invocationId,
                toolInvocationId: $event->toolInvocationId,
                toolName: $this->resolveToolName($event->tool),
                argumentKeys: $this->redactor->redactKeys($this->argumentsAsMap($event->arguments)),
                resultType: get_debug_type($event->result),
                packageConversationId: $agent->telemetryContext->packageConversationId?->toString(),
            ),
        );
    }

    private function resolveProviderName(TextProvider $provider): string
    {
        if (method_exists($provider, 'name')) {
            $name = $provider->name();

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $provider::class;
    }

    private function normalizeString(?string $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function resolveToolName(object $tool): string
    {
        if (method_exists($tool, 'name')) {
            $name = $tool->name();

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return $tool::class;
    }

    /**
     * @param array<mixed> $arguments
     * @return array<string, mixed>
     */
    private function argumentsAsMap(array $arguments): array
    {
        $normalized = [];

        foreach ($arguments as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === '') {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
