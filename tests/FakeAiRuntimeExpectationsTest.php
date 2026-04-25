<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Laravel\Ai\Files\Base64Image;

it('records new ExecutionRequest fields and exposes them through Pest expectations', function (): void {
    $fake = new FakeAiRuntime();
    $fake->queueResult(new ExecutionResult(
        runId: 'run-fake-1',
        output: 'fake response',
        structuredOutput: ['ok' => true],
    ));

    $generationOptions = new GenerationOptions(temperature: 0.4, maxTokens: 128);
    $image = new Base64Image(base64: base64_encode('img'), mimeType: 'image/png');

    $result = $fake->execute(
        new ExecutionRequest(
            runId: 'run-fake-1',
            prompt: 'Test the new ExecutionRequest fields are recorded.',
            generationOptions: $generationOptions,
            attachments: [$image],
            providerToolNames: ['web.search'],
        ),
    );

    expect($fake)
      ->toHaveRuntimeExecutions(1)
      ->toHaveGenerationOptions($generationOptions)
      ->toHaveAttachmentOfType(Base64Image::class)
      ->toHaveRequestedProviderTool('web.search')
      ->and($result)
      ->toHaveStructuredOutput(['ok' => true]);
});

it('returns null structuredOutput when no schema drove the call', function (): void {
    $fake = new FakeAiRuntime();
    $fake->queueResult(new ExecutionResult(runId: 'run-fake-2', output: 'plain'));

    $result = $fake->execute(
        new ExecutionRequest(runId: 'run-fake-2', prompt: 'plain call'),
    );

    expect($result)->toHaveStructuredOutput(null);
});
