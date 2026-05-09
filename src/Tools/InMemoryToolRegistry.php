<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolSchemaException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolUnauthorizedException;

final class InMemoryToolRegistry implements ToolRegistry
{
    /**
     * @var array<string, Tool>
     */
    private array $tools = [];

    /**
     * @param iterable<Tool> $tools
     */
    public function __construct(
        private readonly ToolAuthorizer $authorizer = new DenyAllToolAuthorizer(),
        iterable $tools = [],
    ) {
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

        if (!$this->authorizer->authorizeCustomTool($tool, $input)) {
            throw ToolUnauthorizedException::forName($tool->name());
        }

        return $tool->execute($input);
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name] ?? throw ToolNotRegisteredException::forName($name);
    }

    private function assertValidSchema(Tool $tool): void
    {
        /** @var array<string, mixed> $schema */
        $schema = $tool->inputSchema();

        $this->assertValidSchemaDefinition(
            toolName: $tool->name(),
            definition: $schema,
            path: '$',
            root: true,
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertValidSchemaDefinition(string $toolName, array $definition, string $path, bool $root = false): void
    {
        $type = $definition['type'] ?? null;

        if (!is_string($type) || !$this->isSupportedType($type)) {
            throw InvalidToolSchemaException::because($toolName, "schema [{$path}] must declare a supported [type].");
        }

        if ($root && $type !== 'object') {
            throw InvalidToolSchemaException::because($toolName, 'the root schema type must be [object].');
        }

        $nullable = $definition['nullable'] ?? false;
        if (!is_bool($nullable)) {
            throw InvalidToolSchemaException::because($toolName, "schema [{$path}] nullable must be boolean when provided.");
        }

        $enum = $definition['enum'] ?? null;
        if ($enum !== null) {
            if (!is_array($enum)) {
                throw InvalidToolSchemaException::because($toolName, "schema [{$path}] enum must be an array when provided.");
            }

            foreach ($enum as $value) {
                if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
                    throw InvalidToolSchemaException::because($toolName, "schema [{$path}] enum values must be scalar or null.");
                }
            }
        }

        if ($type === 'object') {
            $this->assertValidObjectSchemaDefinition($toolName, $definition, $path);
        }

        if ($type === 'array' && array_key_exists('items', $definition)) {
            $items = $definition['items'];

            if (!is_array($items)) {
                throw InvalidToolSchemaException::because($toolName, "schema [{$path}.items] must be an array schema.");
            }

            /** @var array<string, mixed> $itemsDefinition */
            $itemsDefinition = $items;
            $this->assertValidSchemaDefinition($toolName, $itemsDefinition, $path . '[]');
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertValidObjectSchemaDefinition(string $toolName, array $definition, string $path): void
    {
        $properties = $definition['properties'] ?? [];

        if (!is_array($properties)) {
            throw InvalidToolSchemaException::because($toolName, "schema [{$path}.properties] must be an array.");
        }

        $required = $definition['required'] ?? [];

        if (!is_array($required)) {
            throw InvalidToolSchemaException::because($toolName, "schema [{$path}.required] must be an array when provided.");
        }

        foreach ($required as $property) {
            if (!is_string($property) || $property === '') {
                throw InvalidToolSchemaException::because($toolName, "schema [{$path}.required] property names must be non-empty strings.");
            }

            if (!array_key_exists($property, $properties)) {
                throw InvalidToolSchemaException::because($toolName, "required property [{$path}.{$property}] is not defined in [properties].");
            }
        }

        foreach ($properties as $property => $propertyDefinition) {
            if (!is_string($property) || $property === '') {
                throw InvalidToolSchemaException::because($toolName, "schema [{$path}.properties] property names must be non-empty strings.");
            }

            if (!is_array($propertyDefinition)) {
                throw InvalidToolSchemaException::because($toolName, "schema [{$path}.{$property}] must be defined as an array schema.");
            }

            /** @var array<string, mixed> $nestedDefinition */
            $nestedDefinition = $propertyDefinition;
            $this->assertValidSchemaDefinition($toolName, $nestedDefinition, $path . '.' . $property);
        }

        $additionalProperties = $definition['additionalProperties'] ?? false;

        if (!is_bool($additionalProperties)) {
            throw InvalidToolSchemaException::because($toolName, "schema [{$path}.additionalProperties] must be boolean when provided.");
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
        /** @var array<string, mixed> $schema */
        $schema = $tool->inputSchema();
        $errors = [];

        $this->validateValue(
            definition: $schema,
            value: $input,
            path: '$',
            errors: $errors,
        );

        if ($errors !== []) {
            throw InvalidToolInputException::withErrors($tool->name(), $errors);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<string> $errors
     */
    private function validateValue(array $definition, mixed $value, string $path, array &$errors): void
    {
        if ($value === null) {
            if (($definition['nullable'] ?? false) === true) {
                return;
            }

            $errors[] = "property [{$path}] must not be null";

            return;
        }

        $type = $definition['type'] ?? null;

        if (!is_string($type) || !$this->isSupportedType($type)) {
            $errors[] = "property [{$path}] has an invalid schema type";

            return;
        }

        if (!$this->matchesType($type, $value)) {
            $actualType = get_debug_type($value);
            $errors[] = "property [{$path}] must be of type [{$type}], [{$actualType}] given";

            return;
        }

        $enum = $definition['enum'] ?? null;
        if (is_array($enum) && !$this->matchesEnum($enum, $value)) {
            $errors[] = "property [{$path}] must match one of the declared enum values";
        }

        if ($type === 'object') {
            if (!is_array($value)) {
                return;
            }

            $this->validateObjectValue($definition, $value, $path, $errors);
        }

        if ($type === 'array' && is_array($value) && isset($definition['items']) && is_array($definition['items'])) {
            /** @var array<string, mixed> $items */
            $items = $definition['items'];

            foreach ($value as $index => $item) {
                $this->validateValue(
                    definition: $items,
                    value: $item,
                    path: $path . '[' . $this->formatPathSegment($index) . ']',
                    errors: $errors,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $value
     * @param list<string> $errors
     */
    private function validateObjectValue(array $definition, array $value, string $path, array &$errors): void
    {
        $properties = $this->schemaProperties($definition);
        $required = $this->schemaRequired($definition);
        $allowAdditionalProperties = $definition['additionalProperties'] ?? false;

        foreach ($required as $property) {
            if (!array_key_exists($property, $value)) {
                $errors[] = "missing required property [{$this->childPath($path, $property)}]";
            }
        }

        foreach ($value as $property => $propertyValue) {
            $propertyName = (string) $property;
            $propertyPath = $this->childPath($path, $propertyName);
            $propertyDefinition = $properties[$propertyName] ?? null;

            if ($propertyDefinition === null) {
                if ($allowAdditionalProperties === false) {
                    $errors[] = "unexpected property [{$propertyPath}]";
                }

                continue;
            }

            $this->validateValue($propertyDefinition, $propertyValue, $propertyPath, $errors);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<string, mixed>>
     */
    private function schemaProperties(array $definition): array
    {
        $properties = $definition['properties'] ?? [];

        if (!is_array($properties)) {
            return [];
        }

        $resolved = [];
        foreach ($properties as $property => $propertyDefinition) {
            if (!is_string($property) || !is_array($propertyDefinition)) {
                continue;
            }

            /** @var array<string, mixed> $propertyDefinition */
            $resolved[$property] = $propertyDefinition;
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function schemaRequired(array $definition): array
    {
        $required = $definition['required'] ?? [];

        if (!is_array($required)) {
            return [];
        }

        return array_values(
            array_filter(
                $required,
                static fn (mixed $property): bool => is_string($property) && $property !== '',
            ),
        );
    }

    /**
     * @param array<int|string, mixed> $enum
     */
    private function matchesEnum(array $enum, mixed $value): bool
    {
        foreach ($enum as $candidate) {
            if ($candidate === $value) {
                return true;
            }
        }

        return false;
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

    private function childPath(string $parent, string $child): string
    {
        return $parent === '$' ? $child : $parent . '.' . $child;
    }

    private function formatPathSegment(mixed $segment): string
    {
        if (is_int($segment) || is_string($segment)) {
            return (string) $segment;
        }

        return get_debug_type($segment);
    }
}
