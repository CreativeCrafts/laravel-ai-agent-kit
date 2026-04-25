<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ProviderToolNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryProviderToolRegistry;
use Laravel\Ai\Providers\Tools\WebSearch;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;

it('starts empty', function () {
    $registry = new InMemoryProviderToolRegistry();

    expect($registry->all())->toBe([])
      ->and($registry->has('anything'))->toBeFalse();
});

it('invokes the factory on every get call', function () {
    $registry = new InMemoryProviderToolRegistry();
    $invocations = 0;

    $registry->register('web-search.default', function () use (&$invocations): WebSearch {
        $invocations++;
        return new WebSearch();
    });

    $first = $registry->get('web-search.default');
    $second = $registry->get('web-search.default');

    expect($invocations)->toBe(2)
      ->and($first)->not->toBe($second)
      ->and($first)->toBeInstanceOf(WebSearch::class)
      ->and($second)->toBeInstanceOf(WebSearch::class);
});

it('exposes registered names via all()', function () {
    $registry = new InMemoryProviderToolRegistry();

    $registry->register('a', fn () => new WebSearch());
    $registry->register('b', fn () => new WebSearch());

    expect($registry->all())->toBe(['a', 'b']);
});

it('raises ProviderToolNotRegisteredException for unknown names', function () {
    $registry = new InMemoryProviderToolRegistry();

    $registry->get('missing');
})->throws(ProviderToolNotRegisteredException::class, 'missing');

it('rejects an empty name on register', function () {
    $registry = new InMemoryProviderToolRegistry();

    $registry->register('', fn () => new WebSearch());
})->throws(InvalidArgumentException::class);

it('is isolated from the custom tool registry', function () {
    $providerRegistry = new InMemoryProviderToolRegistry();
    $customRegistry = new InMemoryToolRegistry();

    $providerRegistry->register('web-search.default', fn () => new WebSearch());

    // Custom registry should not see provider tool names
    expect($customRegistry->has('web-search.default'))->toBeFalse()
      ->and($providerRegistry->has('web-search.default'))->toBeTrue();
});
