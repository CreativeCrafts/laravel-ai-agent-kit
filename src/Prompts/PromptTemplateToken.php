<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

/**
 * @internal
 */
final readonly class PromptTemplateToken
{
    private function __construct(
        public PromptTemplateTokenType $type,
        public string $value,
    ) {
    }

    /** Create a literal template token. */
    public static function literal(string $value): self
    {
        return new self(PromptTemplateTokenType::Literal, $value);
    }

    /** Create a variable template token. */
    public static function variable(string $name): self
    {
        return new self(PromptTemplateTokenType::Variable, $name);
    }
}
