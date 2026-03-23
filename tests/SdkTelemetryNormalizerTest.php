<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryContext;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeToolInvocationCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeToolInvocationStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\SdkTelemetryNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Security\DefaultRedactor;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Tools\Request as ToolRequest;

it('normalizes sdk prompting and completion events into redacted package telemetry', function () {
    Event::fake([
      RuntimeExecutionStarted::class,
      RuntimeExecutionCompleted::class,
    ]);

    /** @var Dispatcher $eventDispatcher */
    $eventDispatcher = Event::getFacadeRoot();

    $normalizer = new SdkTelemetryNormalizer(events: $eventDispatcher, redactor: new DefaultRedactor());

    $telemetryAgent = new RuntimeTelemetryAgent(
        telemetryContext: new RuntimeTelemetryContext(
            runId: 'run-telemetry-001',
            requestedToolNames: [],
            inputKeys: ['secret'],
            metadataKeys: ['trace_id'],
            packageConversationId: null,
            storeConversation: false,
            continueConversation: false,
            projectedMessageCount: 0,
        ),
        instructions: 'You are concise.',
        messages: [],
        tools: [],
    );

    $provider = new class () implements TextProvider {
        public function name(): string
        {
            return 'openai';
        }

        public function prompt(AgentPrompt $prompt): AgentResponse
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function stream(AgentPrompt $prompt): StreamableAgentResponse
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function textGateway(): TextGateway
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function useTextGateway(TextGateway $gateway): self
        {
            return $this;
        }

        public function defaultTextModel(): string
        {
            return 'gpt-4o-mini';
        }

        public function cheapestTextModel(): string
        {
            return 'gpt-4o-mini';
        }

        public function smartestTextModel(): string
        {
            return 'gpt-4o-mini';
        }
    };

    $prompt = new AgentPrompt(
        agent: $telemetryAgent,
        prompt: 'Classify this sensitive support message.',
        attachments: new Collection(),
        provider: $provider,
        model: 'gpt-4o-mini',
        timeout: 15,
    );

    $normalizer->handlePromptingAgent(
        new PromptingAgent(
            invocationId: 'invoke-telemetry-001',
            prompt: $prompt,
        ),
    );

    $response = new AgentResponse(
        invocationId: 'invoke-telemetry-001',
        text: 'Telemetry response',
        usage: new Usage(promptTokens: 5, completionTokens: 7),
        meta: new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );
    $response->conversationId = 'sdk-conv-001';
    $response->messages = new Collection();
    $response->toolCalls = new Collection();
    $response->toolResults = new Collection();
    $response->steps = new Collection();

    $normalizer->handleAgentPrompted(
        new AgentPrompted(
            invocationId: 'invoke-telemetry-001',
            prompt: $prompt,
            response: $response,
        ),
    );

    Event::assertDispatched(RuntimeExecutionStarted::class, function (RuntimeExecutionStarted $event): bool {
        expect($event->runId)
          ->toBe('run-telemetry-001')
          ->and($event->provider)->toBe('openai')
          ->and($event->model)->toBe('gpt-4o-mini')
          ->and($event->requestedToolNames)->toBe([])
          ->and($event->inputKeys)->toBe(['[redacted-key]'])
          ->and($event->metadataKeys)->toBe(['trace_id'])
          ->and($event->packageConversationId)->toBeNull()
          ->and($event->storeConversation)->toBeFalse()
          ->and($event->continueConversation)->toBeFalse()
          ->and($event->projectedMessageCount)->toBe(0)
          ->and($event->promptLength)->toBe(strlen('Classify this sensitive support message.'))
          ->and($event->attachmentCount)->toBe(0)
          ->and($event->timeout)->toBe(15)
          ->and(property_exists($event, 'prompt'))->toBeFalse();

        return true;
    });

    Event::assertDispatched(RuntimeExecutionCompleted::class, function (RuntimeExecutionCompleted $event): bool {
        expect($event->runId)
          ->toBe('run-telemetry-001')
          ->and($event->provider)->toBe('openai')
          ->and($event->model)->toBe('gpt-4o-mini')
          ->and($event->requestedToolNames)->toBe([])
          ->and($event->packageConversationId)->toBeNull()
          ->and($event->sdkConversationId)->toBe('sdk-conv-001')
          ->and($event->projectedMessageCount)->toBe(0)
          ->and($event->promptTokens)->toBe(5)
          ->and($event->completionTokens)->toBe(7)
          ->and($event->totalTokens)->toBe(12)
          ->and($event->outputLength)->toBe(strlen('Telemetry response'))
          ->and(property_exists($event, 'output'))->toBeFalse();

        return true;
    });
});

it('normalizes sdk tool invocation events into redacted package telemetry', function () {
    Event::fake([
      RuntimeToolInvocationStarted::class,
      RuntimeToolInvocationCompleted::class,
    ]);

    /** @var Dispatcher $eventDispatcher */
    $eventDispatcher = Event::getFacadeRoot();

    $normalizer = new SdkTelemetryNormalizer(
        events: $eventDispatcher,
        redactor: new class () implements Redactor {
        public function redactText(string $value): string
        {
            return '[custom:' . strlen($value) . ']';
        }

        public function redactKeys(array $values): array
        {
            return array_map(
                static fn (string $key): string => 'custom:' . $key,
                array_values(array_filter(array_keys($values), static fn (string $key): bool => $key !== '')),
            );
        }
    },
    );

    $telemetryAgent = new RuntimeTelemetryAgent(
        telemetryContext: new RuntimeTelemetryContext(
            runId: 'run-tool-telemetry-001',
            requestedToolNames: ['math.add'],
            inputKeys: ['secret'],
            metadataKeys: ['trace_id'],
            packageConversationId: null,
            storeConversation: false,
            continueConversation: false,
            projectedMessageCount: 0,
        ),
        instructions: 'You are a runtime telemetry test agent.',
        messages: [],
        tools: [],
    );

    $tool = new class () implements Tool {
        public function name(): string
        {
            return 'math.add';
        }

        public function description(): string
        {
            return 'Add two integers.';
        }

        public function handle(ToolRequest $request): string
        {
            return '3';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $normalizer->handleInvokingTool(
        new InvokingTool(
            invocationId: 'invoke-tool-001',
            toolInvocationId: 'tool-call-001',
            agent: $telemetryAgent,
            tool: $tool,
            arguments: ['left' => 1, 'right' => 2],
        ),
    );

    $normalizer->handleToolInvoked(
        new ToolInvoked(
            invocationId: 'invoke-tool-001',
            toolInvocationId: 'tool-call-001',
            agent: $telemetryAgent,
            tool: $tool,
            arguments: ['left' => 1, 'right' => 2],
            result: ['sum' => 3],
        ),
    );

    Event::assertDispatched(RuntimeToolInvocationStarted::class, function (RuntimeToolInvocationStarted $event): bool {
        expect($event->runId)
          ->toBe('run-tool-telemetry-001')
          ->and($event->invocationId)->toBe('invoke-tool-001')
          ->and($event->toolInvocationId)->toBe('tool-call-001')
          ->and($event->toolName)->toBe('math.add')
          ->and($event->argumentKeys)->toBe(['custom:left', 'custom:right'])
          ->and(property_exists($event, 'arguments'))->toBeFalse();

        return true;
    });

    Event::assertDispatched(RuntimeToolInvocationCompleted::class, function (RuntimeToolInvocationCompleted $event): bool {
        expect($event->runId)
          ->toBe('run-tool-telemetry-001')
          ->and($event->invocationId)->toBe('invoke-tool-001')
          ->and($event->toolInvocationId)->toBe('tool-call-001')
          ->and($event->toolName)->toBe('math.add')
          ->and($event->argumentKeys)->toBe(['custom:left', 'custom:right'])
          ->and($event->resultType)->toBe('array')
          ->and(property_exists($event, 'result'))->toBeFalse();

        return true;
    });
});
