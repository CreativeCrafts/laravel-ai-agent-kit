<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolSchemaException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolUnauthorizedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;

it('binds the tool registry contract to the default in-memory implementation', function () {
    expect(app(ToolRegistry::class))
      ->toBeInstanceOf(InMemoryToolRegistry::class)
      ->and(app(ToolAuthorizer::class))->toBeInstanceOf(DenyAllToolAuthorizer::class);
});

it('denies execution by default even for registered tools', function () {
    $registry = new InMemoryToolRegistry();
    $registry->register(
        new class () implements Tool {
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
      },
    );

    expect($registry->has('math.add'))->toBeTrue();

    $registry->execute('math.add', ['left' => 2, 'right' => 3]);
})->throws(ToolUnauthorizedException::class, 'math.add');

it('executes a registered tool when authorization explicitly permits it', function () {
    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
          public function authorize(Tool $tool, array $input): bool
          {
              return true;
          }
      },
        tools: [
        new class () implements Tool {
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
        },
      ],
    );

    expect($registry->execute('math.add', ['left' => 2, 'right' => 3]))->toBe(['sum' => 5]);
});

it('fails with a typed exception when a tool is not registered', function () {
    (new InMemoryToolRegistry())->execute('missing.tool', []);
})->throws(ToolNotRegisteredException::class, 'missing.tool');

it('rejects invalid tool input before execution', function () {
    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
          public function authorize(Tool $tool, array $input): bool
          {
              return true;
          }
      },
        tools: [
        new class () implements Tool {
            public function name(): string
            {
                return 'notify.user';
            }

            public function inputSchema(): array
            {
                return [
                  'type' => 'object',
                  'properties' => [
                    'user_id' => ['type' => 'integer'],
                    'message' => ['type' => 'string'],
                  ],
                  'required' => ['user_id', 'message'],
                  'additionalProperties' => false,
                ];
            }

            public function execute(array $input): array
            {
                return ['ok' => true, 'input' => $input];
            }
        },
      ],
    );

    $registry->execute('notify.user', ['user_id' => '7', 'extra' => true]);
})->throws(InvalidToolInputException::class, 'missing required property [message]');

it('accepts associative arrays for properties declared as array type', function () {
    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
          public function authorize(Tool $tool, array $input): bool
          {
              return true;
          }
      },
        tools: [
        new class () implements Tool {
            public function name(): string
            {
                return 'settings.save';
            }

            public function inputSchema(): array
            {
                return [
                  'type' => 'object',
                  'properties' => [
                    'settings' => ['type' => 'array'],
                  ],
                  'required' => ['settings'],
                  'additionalProperties' => false,
                ];
            }

            public function execute(array $input): array
            {
                return ['ok' => true, 'settings' => $input['settings']];
            }
        },
      ],
    );

    expect($registry->execute('settings.save', ['settings' => ['theme' => 'dark', 'language' => 'en']]))
      ->toBe(['ok' => true, 'settings' => ['theme' => 'dark', 'language' => 'en']]);
});

it('rejects invalid tool schemas at registration time', function () {
    (new InMemoryToolRegistry())->register(
        new class () implements Tool {
          public function name(): string
          {
              return 'broken.tool';
          }

          public function inputSchema(): array
          {
              return [
                'type' => 'object',
                'properties' => [
                  'payload' => [],
                ],
                'required' => ['payload'],
                'additionalProperties' => false,
              ];
          }

          public function execute(array $input): array
          {
              return $input;
          }
      },
    );
})->throws(InvalidToolSchemaException::class, 'supported [type]');
