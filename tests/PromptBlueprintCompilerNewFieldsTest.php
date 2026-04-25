<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use Laravel\Ai\Files\Base64Image;

it('round-trips all four new blueprint fields into the execution request', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello {{name}}.',
      ],
    ]);

    $compiler = new PromptBlueprintCompiler(
        new PromptExecutionMapper($repository),
    );

    $options = new GenerationOptions(temperature: 0.3, maxTokens: 400);
    $image = new Base64Image('img-data', 'image/png');
    $closure = function ($js) {
        return [];
    };

    $request = $compiler->compile(
        LaravelAiAgentKit::prompt('support.reply')
        ->withRunId('run-new-fields-001')
        ->withVersion('1.0.0')
        ->withVariables(['name' => 'Prince'])
        ->withGenerationOptions($options)
        ->withSchema($closure)
        ->withAttachment($image)
        ->withProviderTools(['web-search.default']),
    );

    expect($request)
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($request->generationOptions)->toBe($options)
      ->and($request->schema)->toBe($closure)
      ->and($request->attachments)->toBe([$image])
      ->and($request->providerToolNames)->toBe(['web-search.default']);
});

it('leaves new fields at null/empty when the blueprint has not set them', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello world.',
      ],
    ]);

    $compiler = new PromptBlueprintCompiler(
        new PromptExecutionMapper($repository),
    );

    $request = $compiler->compile(
        LaravelAiAgentKit::prompt('support.reply')
        ->withRunId('run-new-fields-002')
        ->withVersion('1.0.0'),
    );

    expect($request->generationOptions)->toBeNull()
      ->and($request->schema)->toBeNull()
      ->and($request->attachments)->toBe([])
      ->and($request->providerToolNames)->toBe([]);
});
