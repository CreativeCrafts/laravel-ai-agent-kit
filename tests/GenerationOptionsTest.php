<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;

it('constructs with all defaults and an empty providerOptions map', function () {
    $options = new GenerationOptions();

    expect($options->temperature)->toBeNull()
      ->and($options->maxTokens)->toBeNull()
      ->and($options->maxSteps)->toBeNull()
      ->and($options->providerOptions)->toBe([])
      ->and($options->toProviderOptionsMap())->toBe([]);
});

it('keeps typed fields off the raw provider options map', function () {
    $options = new GenerationOptions(
        temperature: 0.25,
        maxTokens: 512,
        maxSteps: 3,
    );

    expect($options->temperature)->toBe(0.25)
      ->and($options->maxTokens)->toBe(512)
      ->and($options->maxSteps)->toBe(3)
      ->and($options->toProviderOptionsMap())->toBe([]);
});

it('preserves explicit providerOptions entries in the map', function () {
    $options = new GenerationOptions(
        temperature: 0.1,
        providerOptions: ['top_p' => 0.9],
    );

    expect($options->toProviderOptionsMap())->toBe([
      'top_p' => 0.9,
    ]);
});

it('resolves nested provider options for the current sdk provider', function () {
    $options = new GenerationOptions(
        providerOptions: [
          'openai' => ['reasoning' => ['effort' => 'medium']],
          'anthropic' => ['thinking' => ['budget_tokens' => 1024]],
        ],
    );

    expect($options->providerOptionsFor('openai', 'openai'))->toBe([
      'reasoning' => ['effort' => 'medium'],
    ])->and($options->providerOptionsFor('anthropic', 'anthropic'))->toBe([
      'thinking' => ['budget_tokens' => 1024],
    ]);
});

it('does not leak scoped provider options to another provider', function () {
    $options = new GenerationOptions(
        providerOptions: [
          'openai' => ['reasoning' => ['effort' => 'medium']],
        ],
    );

    expect($options->providerOptionsFor('anthropic', 'anthropic', ['openai', 'anthropic']))->toBe([]);
});

it('applies unscoped provider options to every attempt for backwards compatibility', function () {
    $options = new GenerationOptions(
        providerOptions: ['top_p' => 0.9],
    );

    expect($options->providerOptionsFor('openai', 'openai'))->toBe(['top_p' => 0.9])
      ->and($options->providerOptionsFor('anthropic', 'anthropic'))->toBe(['top_p' => 0.9]);
});

it('lets request provider options override profile defaults for the current attempt', function () {
    $options = new GenerationOptions(
        providerOptions: [
          'openai' => ['reasoning' => ['effort' => 'high']],
        ],
    );

    $attempt = $options->forProviderAttempt(
        sdkProviderName: 'openai',
        driver: 'openai',
        profileOptions: ['reasoning' => ['effort' => 'medium'], 'service_tier' => 'default'],
        additionalScopeKeys: ['openai'],
    );

    expect($attempt->providerOptions)->toBe([
      'reasoning' => ['effort' => 'high'],
      'service_tier' => 'default',
    ])->and($attempt->maxTokens)->toBeNull();
});

it('rejects temperature below zero', function () {
    new GenerationOptions(temperature: -0.01);
})->throws(InvalidArgumentException::class, 'temperature');

it('rejects temperature above two', function () {
    new GenerationOptions(temperature: 2.01);
})->throws(InvalidArgumentException::class, 'temperature');

it('accepts temperature at the lower bound', function () {
    $options = new GenerationOptions(temperature: 0.0);
    expect($options->temperature)->toBe(0.0);
});

it('accepts temperature at the upper bound', function () {
    $options = new GenerationOptions(temperature: 2.0);
    expect($options->temperature)->toBe(2.0);
});

it('rejects non-positive maxTokens', function () {
    new GenerationOptions(maxTokens: 0);
})->throws(InvalidArgumentException::class, 'maxTokens');

it('rejects non-positive maxSteps', function () {
    new GenerationOptions(maxSteps: 0);
})->throws(InvalidArgumentException::class, 'maxSteps');

it('rejects empty-string keys in providerOptions', function () {
    new GenerationOptions(providerOptions: ['' => 'value']);
})->throws(InvalidArgumentException::class);

it('rejects non-string keys in providerOptions', function () {
    new GenerationOptions(providerOptions: [0 => 'value']);
})->throws(InvalidArgumentException::class);
