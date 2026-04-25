<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
use Laravel\Ai\Files\Base64Image;

it('defaults new fields to null/empty on a fresh blueprint', function () {
    $blueprint = LaravelAiAgentKit::prompt('name');

    expect($blueprint->generationOptions)->toBeNull()
      ->and($blueprint->schema)->toBeNull()
      ->and($blueprint->attachments)->toBe([])
      ->and($blueprint->providerToolNames)->toBe([]);
});

it('withGenerationOptions returns a new blueprint without mutating the receiver', function () {
    $a = LaravelAiAgentKit::prompt('name');
    $options = new GenerationOptions(temperature: 0.5);

    $b = $a->withGenerationOptions($options);

    expect($a->generationOptions)->toBeNull()
      ->and($b->generationOptions)->toBe($options)
      ->and($b)->not->toBe($a);
});

it('withSchema accepts a Closure and passes it through', function () {
    $closure = function ($js) {
        return [];
    };

    $blueprint = LaravelAiAgentKit::prompt('name')->withSchema($closure);

    expect($blueprint->schema)->toBe($closure);
});

it('withSchema accepts a class-string', function () {
    $blueprint = LaravelAiAgentKit::prompt('name')->withSchema(PromptBlueprint::class);

    expect($blueprint->schema)->toBe(PromptBlueprint::class);
});

it('withSchema accepts null to clear', function () {
    $blueprint = LaravelAiAgentKit::prompt('name')
      ->withSchema(PromptBlueprint::class)
      ->withSchema(null);

    expect($blueprint->schema)->toBeNull();
});

it('withAttachment appends in order', function () {
    $a = new Base64Image('a-data', 'image/png');
    $b = new Base64Image('b-data', 'image/png');

    $blueprint = LaravelAiAgentKit::prompt('name')
      ->withAttachment($a)
      ->withAttachment($b);

    expect($blueprint->attachments)->toBe([$a, $b]);
});

it('withAttachments replaces the list entirely', function () {
    $a = new Base64Image('a-data', 'image/png');
    $b = new Base64Image('b-data', 'image/png');

    $blueprint = LaravelAiAgentKit::prompt('name')
      ->withAttachment($a)
      ->withAttachments([$b]);

    expect($blueprint->attachments)->toBe([$b]);
});

it('addProviderTool appends without touching custom toolNames', function () {
    $blueprint = LaravelAiAgentKit::prompt('name')
      ->addTool('custom.search')
      ->addProviderTool('web-search.default');

    expect($blueprint->toolNames)->toBe(['custom.search'])
      ->and($blueprint->providerToolNames)->toBe(['web-search.default']);
});

it('withProviderTools replaces the provider tool list only', function () {
    $blueprint = LaravelAiAgentKit::prompt('name')
      ->addTool('custom.search')
      ->addProviderTool('web-fetch.default')
      ->withProviderTools(['web-search.default']);

    expect($blueprint->toolNames)->toBe(['custom.search'])
      ->and($blueprint->providerToolNames)->toBe(['web-search.default']);
});

it('provider tool list deduplicates and filters empty strings', function () {
    $blueprint = LaravelAiAgentKit::prompt('name')
      ->withProviderTools(['web-search', 'web-search', '', 'web-fetch']);

    expect($blueprint->providerToolNames)->toBe(['web-search', 'web-fetch']);
});

it('new builder methods do not mutate the receiver', function () {
    $base = LaravelAiAgentKit::prompt('name');
    $image = new Base64Image('data', 'image/png');
    $options = new GenerationOptions(temperature: 0.5);

    $base
      ->withGenerationOptions($options)
      ->withSchema(PromptBlueprint::class)
      ->withAttachment($image)
      ->addProviderTool('web-search');

    expect($base->generationOptions)->toBeNull()
      ->and($base->schema)->toBeNull()
      ->and($base->attachments)->toBe([])
      ->and($base->providerToolNames)->toBe([]);
});

it('rejects an empty class-string schema', function () {
    LaravelAiAgentKit::prompt('name')->withSchema('');
})->throws(InvalidArgumentException::class, 'schema class-string must be non-empty');

it('rejects non-File attachments', function () {
    LaravelAiAgentKit::prompt('name')->withAttachments(['not-a-file']);
})->throws(InvalidArgumentException::class, 'attachment at index [0]');
