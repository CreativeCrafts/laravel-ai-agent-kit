<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\BlueprintCompilationException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;

it('compiles a prompt blueprint into a package-owned execution request', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello {{name}}, your ticket is {{ticket_id}}.',
      ],
    ]);

    $compiler = new PromptBlueprintCompiler(
        new PromptExecutionMapper($repository),
    );

    $request = $compiler->compile(
        LaravelAiAgentKit::prompt('support.reply')
        ->withRunId('run-blueprint-compile-001')
        ->withVersion('1.0.0')
        ->withVariables([
          'name' => 'Prince',
          'ticket_id' => 42,
        ])
        ->withInstructions(['You are concise.', 'You are concise.', ''])
        ->usingProvider('openai')
        ->usingModel('gpt-4o-mini')
        ->withTools(['math.add', 'math.add', ''])
        ->withInput(['channel' => 'email'])
        ->withMetadata(['source' => 'blueprint'])
        ->withTimeout(30),
    );

    expect($request)
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($request->runId)->toBe('run-blueprint-compile-001')
      ->and($request->prompt)->toBe('Hello Prince, your ticket is 42.')
      ->and($request->instructions)->toBe(['You are concise.'])
      ->and($request->provider)->toBe('openai')
      ->and($request->model)->toBe('gpt-4o-mini')
      ->and($request->toolNames)->toBe(['math.add'])
      ->and($request->input)->toBe(['channel' => 'email'])
      ->and($request->metadata)->toBe([
        'source' => 'blueprint',
        'prompt_name' => 'support.reply',
        'prompt_version' => '1.0.0',
      ])
      ->and($request->timeout)->toBe(30)
      ->and($request->conversationId)->toBeNull()
      ->and($request->storeConversation)->toBeFalse()
      ->and($request->continueConversation)->toBeFalse();
});

it('compiles prompt blueprint conversation controls into the execution request', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello {{name}}.',
      ],
    ]);

    $compiler = new PromptBlueprintCompiler(
        new PromptExecutionMapper($repository),
    );

    $request = $compiler->compile(
        LaravelAiAgentKit::prompt('support.reply')
        ->withRunId('run-blueprint-conversation-001')
        ->withVersion('1.0.0')
        ->withVariables(['name' => 'Prince'])
        ->continueConversation(new ConversationId('conv-blueprint-001')),
    );

    expect($request->conversationId?->toString())
      ->toBe('conv-blueprint-001')
      ->and($request->storeConversation)->toBeTrue()
      ->and($request->continueConversation)->toBeTrue();
});

it('preserves package-owned run id validation during blueprint compilation', function () {
    $repository = new InMemoryPromptRepository([
      'summary' => [
        '1.0.0' => 'Summarize {{topic}}.',
      ],
    ]);

    $compiler = new PromptBlueprintCompiler(
        new PromptExecutionMapper($repository),
    );

    $compiler->compile(
        LaravelAiAgentKit::prompt('summary')
        ->withVariables(['topic' => 'billing'])
        ->withVersion('1.0.0'),
    );
})->throws(BlueprintCompilationException::class, 'summary');

it('binds the blueprint compiler contract to the prompt blueprint compiler', function () {
    expect(app(BlueprintCompiler::class))->toBeInstanceOf(PromptBlueprintCompiler::class);
});
