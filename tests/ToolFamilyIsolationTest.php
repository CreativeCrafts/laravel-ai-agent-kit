<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Providers\Tools\WebSearch;

it('proceeds when provider tool authorization denies but only custom tools are requested', function (): void {
    app()->register(AiServiceProvider::class);

    app()->bind(ToolAuthorizer::class, function () {
        return new class () implements ToolAuthorizer {
            public function authorizeCustomTool(Tool $tool, array $input): bool
            {
                return true;
            }

            public function authorizeProviderTool(string $providerToolName): bool
            {
                return false;
            }
        };
    });

    app()->forgetInstance(InMemoryToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);
    app()->forgetInstance(ProviderToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(
        new class () implements Tool {
            public function name(): string
            {
                return 'echo';
            }

            public function inputSchema(): array
            {
                return [
                  'type' => 'object',
                  'properties' => [
                    'value' => ['type' => 'string'],
                  ],
                  'required' => ['value'],
                  'additionalProperties' => false,
                ];
            }

            public function execute(array $input): array
            {
                return ['echoed' => $input['value']];
            }
        },
    );

    /** @var ProviderToolRegistry $providerRegistry */
    $providerRegistry = app(ProviderToolRegistry::class);
    $providerRegistry->register('web.search', fn () => new WebSearch());

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-tool-isolation-custom-only',
            prompt: 'Use a local tool only.',
            provider: 'openai',
            toolNames: ['echo'],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return count($tools) === 1
          && $tools[0] instanceof SdkToolAdapter
          && $tools[0]->name() === 'echo';
    });
});

it('materializes both families when each is authorized for a single request', function (): void {
    app()->register(AiServiceProvider::class);

    app()->bind(ToolAuthorizer::class, function () {
        return new class () implements ToolAuthorizer {
            public function authorizeCustomTool(Tool $tool, array $input): bool
            {
                return true;
            }

            public function authorizeProviderTool(string $providerToolName): bool
            {
                return true;
            }
        };
    });

    app()->forgetInstance(InMemoryToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);
    app()->forgetInstance(ProviderToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(
        new class () implements Tool {
            public function name(): string
            {
                return 'lookup';
            }

            public function inputSchema(): array
            {
                return [
                  'type' => 'object',
                  'properties' => [
                    'q' => ['type' => 'string'],
                  ],
                  'required' => ['q'],
                  'additionalProperties' => false,
                ];
            }

            public function execute(array $input): array
            {
                return ['result' => $input['q']];
            }
        },
    );

    /** @var ProviderToolRegistry $providerRegistry */
    $providerRegistry = app(ProviderToolRegistry::class);
    $providerRegistry->register('web.search', fn () => new WebSearch(maxSearches: 1));

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-tool-isolation-both',
            prompt: 'Use both families.',
            provider: 'openai',
            toolNames: ['lookup'],
            providerToolNames: ['web.search'],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        $hasCustom = false;
        $hasProvider = false;

        foreach ($tools as $tool) {
            if ($tool instanceof SdkToolAdapter && $tool->name() === 'lookup') {
                $hasCustom = true;
            }

            if ($tool instanceof WebSearch) {
                $hasProvider = true;
            }
        }

        return count($tools) === 2 && $hasCustom && $hasProvider;
    });
});
