<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;

it('defaults execution required capabilities to an empty backward compatible declaration', function (): void {
    $request = new ExecutionRequest(runId: 'required-capabilities-default', prompt: 'Hello');

    expect($request->requiredCapabilities)->toBe([]);
});

it('preserves required capabilities when deriving runtime request variants', function (): void {
    $request = new ExecutionRequest(
        runId: 'required-capabilities-copy',
        prompt: 'Hello',
        requiredCapabilities: ['text_generation', 'structured_output'],
    );

    expect($request->withMetadata(['trace' => true])->requiredCapabilities)
        ->toBe(['text_generation', 'structured_output'])
        ->and($request->withProviderIdentity('backup', 'model')->requiredCapabilities)
        ->toBe(['text_generation', 'structured_output']);
});

it('rejects invalid execution required capability declarations', function (array $capabilities, string $message): void {
    expect(fn (): ExecutionRequest => new ExecutionRequest(
        runId: 'required-capabilities-invalid',
        prompt: 'Hello',
        requiredCapabilities: $capabilities,
    ))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty capability' => [[''], 'must be a non-empty string'],
    'non-string capability' => [[42], 'must be a non-empty string'],
    'duplicate capability' => [
        ['text_generation', 'text_generation'],
        'contains duplicate [text_generation]',
    ],
]);
