<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolUnauthorizedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Tools\Request as SdkToolRequest;

it('materializes package-governed tools into sdk-compatible tool adapters', function () {
    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(sdkToolMaterializerMathAddTool());

    app()->forgetInstance(SdkToolMaterializer::class);

    /** @var SdkToolMaterializer $materializer */
    $materializer = app(SdkToolMaterializer::class);

    $tools = $materializer->materialize(['math.add', 'math.add']);

    expect($tools)
      ->toHaveCount(1)
      ->and($tools[0])->toBeInstanceOf(SdkToolAdapter::class)
      ->and($tools[0]->name())->toBe('math.add')
      ->and((string)$tools[0]->description())->toContain('math.add');
});

it('preserves package authorization when an sdk tool adapter is invoked', function () {
    $registry = new InMemoryToolRegistry();
    $registry->register(sdkToolMaterializerMathAddTool());

    $adapter = new SdkToolAdapter(
        tool: $registry->get('math.add'),
        toolRegistry: $registry,
    );

    $adapter->handle(new SdkToolRequest(['left' => 2, 'right' => 3]));
})->throws(ToolUnauthorizedException::class, 'math.add');

it('serializes sdk tool adapter results through the package registry execution path', function () {
    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
          public function authorizeCustomTool(Tool $tool, array $input): bool
          {
              return true;
          }

          public function authorizeProviderTool(string $providerToolName): bool
          {
              return true;
          }
      },
        tools: [sdkToolMaterializerMathAddTool()],
    );

    $adapter = new SdkToolAdapter(
        tool: $registry->get('math.add'),
        toolRegistry: $registry,
    );

    expect($adapter->handle(new SdkToolRequest(['left' => 2, 'right' => 3])))
      ->toBe('{"sum":5}');
});

it('materializes explicitly configured provider-native tools', function () {
    $authorizer = new class () implements ToolAuthorizer {
        public function authorizeCustomTool(Tool $tool, array $input): bool
        {
            return true;
        }

        public function authorizeProviderTool(string $providerToolName): bool
        {
            return true;
        }
    };

    app()->bind(ToolAuthorizer::class, fn () => $authorizer);
    app()->forgetInstance(ProviderToolMaterializer::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);
    $registry->register('web.search', fn () => new WebSearch(maxSearches: 3, allowedDomains: ['example.com']));

    /** @var ProviderToolMaterializer $materializer */
    $materializer = app(ProviderToolMaterializer::class);

    $tools = $materializer->materialize(['web.search']);

    expect($tools)
      ->toHaveCount(1)
      ->and($tools[0])->toBeInstanceOf(WebSearch::class)
      ->and($tools[0]->maxSearches)->toBe(3)
      ->and($tools[0]->allowedDomains)->toBe(['example.com']);
});

it('materializes explicitly configured web fetch provider-native tools', function () {
    $authorizer = new class () implements ToolAuthorizer {
        public function authorizeCustomTool(Tool $tool, array $input): bool
        {
            return true;
        }

        public function authorizeProviderTool(string $providerToolName): bool
        {
            return true;
        }
    };

    app()->bind(ToolAuthorizer::class, fn () => $authorizer);
    app()->forgetInstance(ProviderToolMaterializer::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);
    $registry->register('web.fetch', fn () => new WebFetch(maxSearches: 2, allowedDomains: ['example.com']));

    /** @var ProviderToolMaterializer $materializer */
    $materializer = app(ProviderToolMaterializer::class);

    $tools = $materializer->materialize(['web.fetch']);

    expect($tools)
      ->toHaveCount(1)
      ->and($tools[0])->toBeInstanceOf(WebFetch::class)
      ->and($tools[0]->maxSearches)->toBe(2)
      ->and($tools[0]->allowedDomains)->toBe(['example.com']);
});

it('fails fast when a requested tool cannot be materialized', function () {
    app()->forgetInstance(SdkToolMaterializer::class);

    /** @var SdkToolMaterializer $materializer */
    $materializer = app(SdkToolMaterializer::class);

    $materializer->materialize(['missing.tool']);
})->throws(ToolNotRegisteredException::class, 'missing.tool');

function sdkToolMaterializerMathAddTool(): Tool
{
    return new class () implements Tool {
        public function name(): string
        {
            return 'math.add';
        }

        public function inputSchema(): array
        {
            return [
              'type' => 'object',
              'properties' => [
                'left' => ['type' => 'integer'],
                'right' => ['type' => 'integer'],
              ],
              'required' => ['left', 'right'],
              'additionalProperties' => false,
            ];
        }

        public function execute(array $input): array
        {
            return ['sum' => $input['left'] + $input['right']];
        }
    };
}
