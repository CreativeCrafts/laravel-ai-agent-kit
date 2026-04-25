<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryProviderToolRegistry;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

it('pre-registers configured provider tools out of the box', function (): void {
    config()->set('ai-agent-kit.tools.provider_tools', [
      'web.search' => [
        'type' => 'web_search',
        'enabled' => true,
        'max_searches' => 3,
        'allowed_domains' => ['example.com'],
        'location' => [
          'city' => 'Stockholm',
          'region' => 'Stockholm County',
          'country' => 'SE',
        ],
      ],
      'web.fetch' => [
        'type' => 'web_fetch',
        'enabled' => true,
        'allowed_domains' => ['docs.example.com'],
      ],
      'docs.search' => [
        'type' => 'file_search',
        'enabled' => true,
        'stores' => ['store_123'],
        'filters' => ['scope' => 'support'],
      ],
    ]);

    app()->forgetInstance(InMemoryProviderToolRegistry::class);
    app()->forgetInstance(ProviderToolRegistry::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);

    expect($registry->all())->toEqualCanonicalizing(['web.search', 'web.fetch', 'docs.search']);

    $webSearch = $registry->get('web.search');
    $webFetch = $registry->get('web.fetch');
    $docsSearch = $registry->get('docs.search');

    expect($webSearch)
      ->toBeInstanceOf(WebSearch::class)
      ->and($webSearch->maxSearches)->toBe(3)
      ->and($webSearch->allowedDomains)->toBe(['example.com'])
      ->and($webSearch->city)->toBe('Stockholm')
      ->and($webFetch)->toBeInstanceOf(WebFetch::class)
      ->and($webFetch->allowedDomains)->toBe(['docs.example.com'])
      ->and($docsSearch)->toBeInstanceOf(FileSearch::class)
      ->and($docsSearch->ids())->toBe(['store_123']);
});

it('skips disabled provider tool entries', function (): void {
    config()->set('ai-agent-kit.tools.provider_tools', [
      'web.search' => [
        'type' => 'web_search',
        'enabled' => false,
      ],
    ]);

    app()->forgetInstance(InMemoryProviderToolRegistry::class);
    app()->forgetInstance(ProviderToolRegistry::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);

    expect($registry->has('web.search'))->toBeFalse();
});
