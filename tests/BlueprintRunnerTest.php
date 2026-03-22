<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\CompiledBlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\AnonymousAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;

it('runs a prompt blueprint through the sdk-backed runtime bridge using package-owned fluent apis', function () {
    app()->register(AiServiceProvider::class);

    app()->instance(PromptRepository::class, new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Reply to {{name}} about {{topic}}.',
      ],
    ]));
    app()->forgetInstance(BlueprintCompiler::class);
    app()->forgetInstance(PromptBlueprintCompiler::class);
    app()->forgetInstance(BlueprintRunner::class);
    app()->forgetInstance(CompiledBlueprintRunner::class);

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

    Ai::fakeAgent(AnonymousAgent::class, ['Blueprint runner response'])->preventStrayPrompts();

    /** @var BlueprintRunner $runner */
    $runner = app(BlueprintRunner::class);

    $result = $runner->run(
        LaravelAiAgentKit::prompt('support.reply')
        ->withRunId('run-blueprint-runner-001')
        ->withVersion('1.0.0')
        ->withVariables([
          'name' => 'Prince',
          'topic' => 'account verification',
        ])
        ->withInstructions(['You are a helpful support assistant.'])
        ->usingProvider('openai')
        ->usingModel('gpt-4o-mini')
        ->withTools(['math.add'])
        ->withInput(['channel' => 'chat'])
        ->withMetadata(['source' => 'blueprint-runner'])
        ->withTimeout(30),
    );

    expect($result->runId)
      ->toBe('run-blueprint-runner-001')
      ->and($result->output)->toBe('Blueprint runner response')
      ->and($result->metadata['requested_tool_names'])->toBe(['math.add'])
      ->and($result->metadata['materialized_tool_count'])->toBe(1);

    Ai::assertAgentWasPrompted(AnonymousAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return $prompt->prompt === 'Reply to Prince about account verification.'
          && count($tools) === 1
          && $tools[0] instanceof SdkToolAdapter
          && $tools[0]->name() === 'math.add';
    });
});

it('binds the blueprint runner contract to the compiled blueprint runner', function () {
    expect(app(BlueprintRunner::class))->toBeInstanceOf(CompiledBlueprintRunner::class);
});
