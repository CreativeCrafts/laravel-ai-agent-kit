<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Providers\Tools\WebSearch;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;

it('binds the ai runtime contract to the sdk ai runtime', function () {
    app()->register(AiServiceProvider::class);

    $runtime = app(AiRuntime::class);

    expect($runtime)->toBeInstanceOf(SdkAiRuntime::class);
});

it('executes a runtime request through the laravel ai sdk bridge', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(AnonymousAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-001',
            prompt: 'Summarize this text.',
            instructions: ['You are a concise assistant.'],
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    );

    expect($result)
      ->toBeInstanceOf(ExecutionResult::class)
      ->and($result->runId)->toBe('run-bridge-001')
      ->and($result->output)->toBe('Bridge response')
      ->and($result->provider)->toBe('openai')
      ->and($result->model)->toBe('gpt-4o-mini')
      ->and($result->usage)
      ->toHaveKey('prompt_tokens')
      ->toHaveKey('completion_tokens')
      ->and($result->metadata)
      ->toHaveKey('invocation_id')
      ->toHaveKey('requested_tool_names')
      ->toHaveKey('materialized_tool_count')
      ->and($result->metadata['requested_tool_names'])->toBe([])
      ->and($result->metadata['materialized_tool_count'])->toBe(0);
});

it('materializes package-governed tools into the sdk agent prompt', function () {
    app()->register(AiServiceProvider::class);

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(
        new class () implements Tool {
          public function name(): string
          {
              return 'math.add';
          }

          public function inputSchema(): array
          {
              return [
                'type' => 'object',
                'properties' => [
                  'left' => ['type' => 'integer'],
                  'right' => ['type' => 'integer'],
                ],
                'required' => ['left', 'right'],
                'additionalProperties' => false,
              ];
          }

          public function execute(array $input): array
          {
              return ['sum' => $input['left'] + $input['right']];
          }
      },
    );

    Ai::fakeAgent(AnonymousAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-tools-001',
            prompt: 'Use the calculator tool if needed.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolNames: ['math.add'],
        ),
    );

    Ai::assertAgentWasPrompted(AnonymousAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return count($tools) === 1
          && $tools[0] instanceof SdkToolAdapter
          && $tools[0]->name() === 'math.add';
    });
});

it('materializes explicitly configured provider-native tools into the sdk agent prompt', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.tools.provider_tools', [
      'web.search' => [
        'type' => 'web_search',
        'enabled' => true,
        'max_searches' => 2,
        'allowed_domains' => ['example.com'],
      ],
    ]);

    app()->forgetInstance(SdkToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    Ai::fakeAgent(AnonymousAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-provider-tools-001',
            prompt: 'Search the web for the latest update.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolNames: ['web.search'],
        ),
    );

    Ai::assertAgentWasPrompted(AnonymousAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return count($tools) === 1
          && $tools[0] instanceof WebSearch
          && $tools[0]->maxSearches === 2
          && $tools[0]->allowedDomains === ['example.com'];
    });
});

it('wraps missing tool materialization failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-bridge-missing-tool',
                prompt: 'Attempt to use a missing tool.',
                provider: 'openai',
                toolNames: ['missing.tool'],
            ),
        ))
      ->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-bridge-missing-tool]');
});

it('wraps sdk runtime failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(AnonymousAgent::class, [
      static function (): never {
          throw new RuntimeException('SDK failure');
      },
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-bridge-failure',
                prompt: 'Fail this request.',
                provider: 'openai',
            ),
        ))
      ->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-bridge-failure]');
});
