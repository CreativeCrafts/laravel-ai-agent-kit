<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool as PackageTool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Contracts\Tool as SdkTool;
use Laravel\Ai\Tools\Request;

final readonly class SdkToolAdapter implements SdkTool
{
    public function __construct(
        private PackageTool $tool,
        private ToolRegistry $toolRegistry,
    ) {
    }

    public function handle(Request $request): string
    {
        $result = $this->toolRegistry->execute($this->tool->name(), $this->normalizeArguments($request));

        try {
            return json_encode($result, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(sprintf('Tool [%s] returned a non-serializable payload.', $this->tool->name()), $exception->getCode(), previous: $exception);
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $toolSchema = $this->tool->inputSchema();
        $properties = $toolSchema['properties'] ?? [];

        if (!is_array($properties)) {
            return [];
        }

        $required = $this->requiredProperties($toolSchema);
        $resolved = [];

        foreach ($properties as $name => $definition) {
            if (!is_string($name)) {
                continue;
            }
            if ($name === '') {
                continue;
            }
            if (!is_array($definition)) {
                continue;
            }
            /** @var array<string, mixed> $propertyDefinition */
            $propertyDefinition = $definition;

            $resolved[$name] = $this->mapDefinition(
                schema: $schema,
                definition: $propertyDefinition,
                required: in_array($name, $required, true),
            );
        }

        return $resolved;
    }

    public function description(): string
    {
        $schema = $this->tool->inputSchema();
        $description = $schema['description'] ?? null;

        if (is_string($description) && $description !== '') {
            return $description;
        }

        $requiredProperties = $this->requiredProperties($schema);

        if ($requiredProperties === []) {
            return sprintf('Execute the package-governed tool [%s].', $this->tool->name());
        }

        return sprintf(
            'Execute the package-governed tool [%s]. Required inputs: %s.',
            $this->tool->name(),
            implode(', ', $requiredProperties),
        );
    }

    public function name(): string
    {
        return $this->tool->name();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArguments(Request $request): array
    {
        $normalized = [];

        foreach ($request->toArray() as $key => $value) {
            $normalized[(string)$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function mapDefinition(JsonSchema $schema, array $definition, bool $required): Type
    {
        $typeName = $definition['type'] ?? null;

        if (!is_string($typeName)) {
            throw new InvalidArgumentException('Tool schema definitions must declare a string [type].');
        }

        $type = match ($typeName) {
            'string' => $schema->string(),
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            'array' => $this->mapArrayDefinition($schema, $definition),
            'object' => $this->mapObjectDefinition($schema, $definition),
            default => throw new InvalidArgumentException('Unsupported tool schema type.'),
        };

        return $this->applyCommonMetadata($type, $definition, $required);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function mapArrayDefinition(JsonSchema $schema, array $definition): Type
    {
        $type = $schema->array();
        $items = $definition['items'] ?? null;

        if (is_array($items)) {
            /** @var array<string, mixed> $itemDefinition */
            $itemDefinition = $items;

            $type = $type->items($this->mapDefinition($schema, $itemDefinition, false));
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function mapObjectDefinition(JsonSchema $schema, array $definition): Type
    {
        $properties = $definition['properties'] ?? [];
        $required = $this->requiredProperties($definition);
        $resolved = [];

        if (is_array($properties)) {
            foreach ($properties as $name => $propertyDefinition) {
                if (!is_string($name)) {
                    continue;
                }
                if ($name === '') {
                    continue;
                }
                if (!is_array($propertyDefinition)) {
                    continue;
                }
                /** @var array<string, mixed> $nestedPropertyDefinition */
                $nestedPropertyDefinition = $propertyDefinition;

                $resolved[$name] = $this->mapDefinition(
                    schema: $schema,
                    definition: $nestedPropertyDefinition,
                    required: in_array($name, $required, true),
                );
            }
        }

        $type = $schema->object($resolved);

        if (($definition['additionalProperties'] ?? true) === false) {
            return $type->withoutAdditionalProperties();
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function applyCommonMetadata(Type $type, array $definition, bool $required): Type
    {
        if ($required) {
            $type = $type->required();
        }

        if (($definition['nullable'] ?? false) === true) {
            $type = $type->nullable();
        }

        if (isset($definition['title']) && is_string($definition['title']) && $definition['title'] !== '') {
            $type = $type->title($definition['title']);
        }

        if (isset($definition['description']) && is_string($definition['description']) && $definition['description'] !== '') {
            $type = $type->description($definition['description']);
        }

        $enum = $definition['enum'] ?? null;

        if (is_array($enum)) {
            $normalizedEnum = array_values(
                array_filter(
                    $enum,
                    static fn (mixed $value): bool
                    => is_string($value)
                  || is_int($value)
                  || is_float($value)
                  || is_bool($value),
                ),
            );

            if ($normalizedEnum !== []) {
                $type = $type->enum($normalizedEnum);
            }
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $schema
     * @return list<string>
     */
    private function requiredProperties(array $schema): array
    {
        $required = $schema['required'] ?? [];

        if (!is_array($required)) {
            return [];
        }

        return array_values(
            array_filter(
                $required,
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            ),
        );
    }
}
