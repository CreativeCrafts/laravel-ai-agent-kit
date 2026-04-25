<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Tools\AbstractToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Tools\ToolKind;

function toolAuthorizerSplitTestDummyTool(): Tool
{
    return new class () implements Tool {
        public function name(): string
        {
            return 'dummy.tool';
        }

        public function inputSchema(): array
        {
            return ['type' => 'object', 'properties' => [], 'required' => []];
        }

        public function execute(array $input): array
        {
            return ['ok' => true];
        }
    };
}

it('DenyAllToolAuthorizer denies custom tools', function () {
    $authorizer = new DenyAllToolAuthorizer();

    expect($authorizer->authorizeCustomTool(toolAuthorizerSplitTestDummyTool(), []))->toBeFalse();
});

it('DenyAllToolAuthorizer denies provider tools', function () {
    $authorizer = new DenyAllToolAuthorizer();

    expect($authorizer->authorizeProviderTool('web-search.default'))->toBeFalse();
});

it('AbstractToolAuthorizer routes custom tool calls through the single authorize method', function () {
    $calls = [];

    $authorizer = new class ($calls) extends AbstractToolAuthorizer {
        public function __construct(private array &$calls)
        {
        }

        protected function authorize(ToolKind $kind, string $name, ?Tool $tool, array $input): bool
        {
            $this->calls[] = [$kind, $name, $tool?->name(), $input];
            return true;
        }
    };

    $tool = toolAuthorizerSplitTestDummyTool();
    $result = $authorizer->authorizeCustomTool($tool, ['x' => 1]);

    expect($result)->toBeTrue()
      ->and($calls)->toHaveCount(1)
      ->and($calls[0])->toBe([ToolKind::Custom, 'dummy.tool', 'dummy.tool', ['x' => 1]]);
});

it('AbstractToolAuthorizer routes provider tool calls through the single authorize method', function () {
    $calls = [];

    $authorizer = new class ($calls) extends AbstractToolAuthorizer {
        public function __construct(private array &$calls)
        {
        }

        protected function authorize(ToolKind $kind, string $name, ?Tool $tool, array $input): bool
        {
            $this->calls[] = [$kind, $name, $tool, $input];
            return false;
        }
    };

    $result = $authorizer->authorizeProviderTool('web-search.default');

    expect($result)->toBeFalse()
      ->and($calls)->toHaveCount(1)
      ->and($calls[0])->toBe([ToolKind::Provider, 'web-search.default', null, []]);
});
