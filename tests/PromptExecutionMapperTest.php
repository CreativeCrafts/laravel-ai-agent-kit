<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;

it('maps a rendered prompt into a package-owned execution request', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello {{name}}, your ticket is {{ticket_id}}.',
        '2.1.0' => 'Hi {{name}} — case {{ticket_id}} is assigned to {{agent}}.',
      ],
    ]);

    $mapper = new PromptExecutionMapper($repository);

    $request = $mapper->mapToExecutionRequest(
        name: 'support.reply',
        runId: 'run-prompt-map-001',
        variables: [
        'name' => 'Prince',
        'ticket_id' => 42,
        'agent' => 'Ada',
      ],
        instructions: ['You are concise.', ''],
        provider: 'openai',
        model: 'gpt-4o-mini',
        toolNames: ['math.add'],
        input: ['channel' => 'email'],
        metadata: ['source' => 'support-workflow'],
        timeout: 30,
    );

    expect($request)
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($request->runId)->toBe('run-prompt-map-001')
      ->and($request->prompt)->toBe('Hi Prince — case 42 is assigned to Ada.')
      ->and($request->instructions)->toBe(['You are concise.'])
      ->and($request->provider)->toBe('openai')
      ->and($request->model)->toBe('gpt-4o-mini')
      ->and($request->toolNames)->toBe(['math.add'])
      ->and($request->input)->toBe(['channel' => 'email'])
      ->and($request->timeout)->toBe(30)
      ->and($request->metadata)->toBe([
        'source' => 'support-workflow',
        'prompt_name' => 'support.reply',
        'prompt_version' => '2.1.0',
      ]);
});

it('preserves package-owned missing-variable failures during mapping', function () {
    $repository = new InMemoryPromptRepository([
      'summary' => [
        '1.0.0' => 'User: {{user}} | Topic: {{topic}}',
      ],
    ]);

    $mapper = new PromptExecutionMapper($repository);

    $mapper->mapToExecutionRequest(
        name: 'summary',
        runId: 'run-prompt-map-missing',
        variables: ['user' => 'Prince'],
        version: '1.0.0',
    );
})->throws(MissingPromptVariableException::class, 'topic');

it('preserves escaped and inserted placeholder syntax when mapping a prompt', function () {
    $repository = new InMemoryPromptRepository([
      'security.example' => [
        '1.0.0' => 'Inspect \\{{payload}} and preserve {{value}}.',
      ],
    ]);
    $mapper = new PromptExecutionMapper($repository);

    $request = $mapper->mapToExecutionRequest(
        name: 'security.example',
        runId: 'run-prompt-map-literal',
        variables: ['value' => '{{other}}'],
    );

    expect($request->prompt)
      ->toBe('Inspect {{payload}} and preserve {{other}}.')
      ->and($request->metadata['prompt_version'])->toBe('1.0.0');
});

it('maps prompt repository output into a request the sdk runtime can execute', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Mapped runtime response'])->preventStrayPrompts();

    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Reply to {{name}} about {{topic}}.',
      ],
    ]);

    $mapper = new PromptExecutionMapper($repository);

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $request = $mapper->mapToExecutionRequest(
        name: 'support.reply',
        runId: 'run-prompt-map-runtime',
        variables: [
        'name' => 'Prince',
        'topic' => 'account verification',
      ],
        version: '1.0.0',
        instructions: ['You are a helpful support assistant.'],
        provider: 'openai',
        model: 'gpt-4o-mini',
    );

    $result = $runtime->execute($request);

    expect($result->runId)
      ->toBe('run-prompt-map-runtime')
      ->and($result->output)->toBe('Mapped runtime response')
      ->and($request->prompt)->toBe('Reply to Prince about account verification.')
      ->and($request->metadata['prompt_name'])->toBe('support.reply')
      ->and($request->metadata['prompt_version'])->toBe('1.0.0');
});
