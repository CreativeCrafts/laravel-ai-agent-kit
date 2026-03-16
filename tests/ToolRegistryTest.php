<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolSchemaException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;

it('binds the tool registry contract to the default in-memory implementation', function () {
    expect(app(ToolRegistry::class))->toBeInstanceOf(InMemoryToolRegistry::class);
});

it('executes only explicitly registered tools', function () {
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

    expect($registry->has('math.add'))
      ->toBeTrue()
      ->and($registry->execute('math.add', ['left' => 2, 'right' => 3]))->toBe(['sum' => 5]);
});

it('fails with a typed exception when a tool is not registered', function () {
    (new InMemoryToolRegistry())->execute('missing.tool', []);
})->throws(ToolNotRegisteredException::class, 'missing.tool');

it('rejects invalid tool input before execution', function () {
    $registry = new InMemoryToolRegistry([
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
    ]);

    $registry->execute('notify.user', ['user_id' => '7', 'extra' => true]);
})->throws(InvalidToolInputException::class, 'missing required property [message]');

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
