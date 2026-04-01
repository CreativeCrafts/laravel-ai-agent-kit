<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;

final class FakeToolRunner implements ToolRegistry
{
    /**
     * @var list<array{name:string, input:array<string, mixed>}>
     */
    private array $executions = [];

    private InMemoryToolRegistry $registry;

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(iterable $tools = [])
    {
        $this->registry = new InMemoryToolRegistry(
            authorizer: new class () implements ToolAuthorizer {
              public function authorize(Tool $tool, array $input): bool
              {
                  return true;
              }
          },
            tools: $tools,
        );
    }

    /**
     * @param array<string, mixed>|Closure(array<string, mixed>): array<string, mixed> $result
     * @param array<string, mixed>|null $schema
     */
    public function stub(string $name, array|Closure $result, ?array $schema = null): self
    {
        $tool = new class ($name, $schema ?? [
          'type' => 'object',
          'properties' => [],
          'required' => [],
          'additionalProperties' => true,
        ], $result) implements Tool {
            private string $name;

            /**
             * @var array<string, mixed>
             */
            private array $schema;

            /**
             * @var array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>
             */
            private array|Closure $result;

            /**
             * @param array<string, mixed> $schema
             * @param array<string, mixed>|Closure(array<string, mixed>): array<string, mixed> $result
             */
            public function __construct(string $name, array $schema, array|Closure $result)
            {
                $this->name = $name;
                $this->schema = $schema;
                $this->result = $result;
            }

            public function name(): string
            {
                return $this->name;
            }

            /**
             * @return array<string, mixed>
             */
            public function inputSchema(): array
            {
                return $this->schema;
            }

            /**
             * @param array<string, mixed> $input
             * @return array<string, mixed>
             */
            public function execute(array $input): array
            {
                if ($this->result instanceof Closure) {
                    return ($this->result)($input);
                }

                return $this->result;
            }
        };

        $this->register($tool);

        return $this;
    }

    public function register(Tool $tool): void
    {
        $this->registry->register($tool);
    }

    public function has(string $name): bool
    {
        return $this->registry->has($name);
    }

    public function get(string $name): Tool
    {
        return $this->registry->get($name);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $name, array $input): array
    {
        $this->executions[] = [
          'name' => $name,
          'input' => $input,
        ];

        return $this->registry->execute($name, $input);
    }

    /**
     * @return list<array{name:string, input:array<string, mixed>}>
     */
    public function executions(): array
    {
        return $this->executions;
    }

    /**
     * @return array{name:string, input:array<string, mixed>}|null
     */
    public function lastExecution(): ?array
    {
        $lastExecutionIndex = array_key_last($this->executions);

        return $lastExecutionIndex !== null ? $this->executions[$lastExecutionIndex] : null;
    }

    public function reset(): void
    {
        $this->executions = [];
        $this->registry = new InMemoryToolRegistry(
            authorizer: new class () implements ToolAuthorizer {
              public function authorize(Tool $tool, array $input): bool
              {
                  return true;
              }
          },
        );
    }
}
