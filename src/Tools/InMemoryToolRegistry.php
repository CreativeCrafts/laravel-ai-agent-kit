<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolSchemaException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;

final class InMemoryToolRegistry implements ToolRegistry
{
    /**
     * @var array<string, Tool>
     */
    private array $tools = [];

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): void
    {
        $this->assertValidSchema($tool);

        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->tools);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $name, array $input): array
    {
        $tool = $this->get($name);

        $this->assertValidInput($tool, $input);

        return $tool->execute($input);
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name] ?? throw ToolNotRegisteredException::forName($name);
    }

    private function assertValidSchema(Tool $tool): void
    {
        $schema = $tool->inputSchema();
        $name = $tool->name();

        if (($schema['type'] ?? null) !== 'object') {
            throw InvalidToolSchemaException::because($name, 'the root schema type must be [object].');
        }

        $properties = $schema['properties'] ?? null;

        if (!is_array($properties)) {
            throw InvalidToolSchemaException::because($name, 'the [properties] key must be an array.');
        }

        $required = $schema['required'] ?? [];

        if (!is_array($required)) {
            throw InvalidToolSchemaException::because($name, 'the [required] key must be an array when provided.');
        }

        foreach ($required as $property) {
            if (!is_string($property) || $property === '') {
                throw InvalidToolSchemaException::because($name, 'required property names must be non-empty strings.');
            }

            if (!array_key_exists($property, $properties)) {
                throw InvalidToolSchemaException::because($name, "required property [{$property}] is not defined in [properties].");
            }
        }

        foreach ($properties as $property => $definition) {
            if (!is_string($property) || $property === '') {
                throw InvalidToolSchemaException::because($name, 'property names must be non-empty strings.');
            }

            if (!is_array($definition)) {
                throw InvalidToolSchemaException::because($name, "property [{$property}] must be defined as an array schema.");
            }

            $type = $definition['type'] ?? null;

            if (!is_string($type) || !$this->isSupportedType($type)) {
                throw InvalidToolSchemaException::because($name, "property [{$property}] must declare a supported [type].");
            }
        }

        $additionalProperties = $schema['additionalProperties'] ?? false;

        if (!is_bool($additionalProperties)) {
            throw InvalidToolSchemaException::because($name, 'the [additionalProperties] key must be a boolean when provided.');
        }
    }

    private function isSupportedType(string $type): bool
    {
        return in_array($type, ['string', 'integer', 'number', 'boolean', 'array', 'object'], true);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function assertValidInput(Tool $tool, array $input): void
    {
        $schema = $tool->inputSchema();
        $properties = $this->schemaProperties($tool);
        $required = $this->schemaRequired($tool);
        $allowAdditionalProperties = $schema['additionalProperties'] ?? false;

        $errors = [];

        foreach ($required as $property) {
            if (!array_key_exists($property, $input)) {
                $errors[] = "missing required property [{$property}]";
            }
        }

        foreach ($input as $property => $value) {
            $definition = $properties[$property] ?? null;

            if ($definition === null) {
                if ($allowAdditionalProperties === false) {
                    $errors[] = "unexpected property [{$property}]";
                }

                continue;
            }

            if (!$this->matchesType($definition['type'], $value)) {
                $actualType = get_debug_type($value);
                $errors[] = "property [{$property}] must be of type [{$definition['type']}], [{$actualType}] given";
            }
        }

        if ($errors !== []) {
            throw InvalidToolInputException::withErrors($tool->name(), $errors);
        }
    }

    /**
     * @return array<string, array{type:string}>
     */
    private function schemaProperties(Tool $tool): array
    {
        /** @var array<string, array{type:string}> $properties */
        $properties = $tool->inputSchema()['properties'];

        return $properties;
    }

    /**
     * @return list<string>
     */
    private function schemaRequired(Tool $tool): array
    {
        /** @var list<string> $required */
        $required = $tool->inputSchema()['required'] ?? [];

        return $required;
    }

    private function matchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            default => false,
        };
    }
}
