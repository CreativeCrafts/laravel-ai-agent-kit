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

it('accepts valid typed fields and maps them into the provider options map', function () {
    $options = new GenerationOptions(
        temperature: 0.25,
        maxTokens: 512,
        maxSteps: 3,
    );

    expect($options->toProviderOptionsMap())->toBe([
      'temperature' => 0.25,
      'maxTokens' => 512,
      'maxSteps' => 3,
    ]);
});

it('omits null typed fields from the provider options map', function () {
    $options = new GenerationOptions(
        temperature: 0.1,
        maxTokens: null,
        maxSteps: null,
    );

    expect($options->toProviderOptionsMap())->toBe(['temperature' => 0.1]);
});

it('preserves explicit providerOptions entries in the map', function () {
    $options = new GenerationOptions(
        temperature: 0.1,
        providerOptions: ['top_p' => 0.9],
    );

    expect($options->toProviderOptionsMap())->toBe([
      'temperature' => 0.1,
      'top_p' => 0.9,
    ]);
});

it('lets explicit providerOptions entries override typed fields on key collision', function () {
    $options = new GenerationOptions(
        temperature: 0.1,
        providerOptions: ['temperature' => 0.9],
    );

    expect($options->toProviderOptionsMap())->toBe(['temperature' => 0.9]);
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
