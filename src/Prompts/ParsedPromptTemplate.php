<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

/**
 * @internal
 */
final readonly class ParsedPromptTemplate
{
    /**
     * @param list<PromptTemplateToken> $tokens
     * @param list<string> $variables
     */
    public function __construct(
        public array $tokens,
        public array $variables,
    ) {
    }

    /**
     * Render the parsed token stream without recursively interpolating inserted values.
     *
     * @param array<string, scalar|null> $variables
     */
    public function render(array $variables): string
    {
        $rendered = '';

        foreach ($this->tokens as $token) {
            if ($token->type === PromptTemplateTokenType::Literal) {
                $rendered .= $token->value;

                continue;
            }

            $value = $variables[$token->value] ?? null;
            $rendered .= $value === null ? '' : (string)$value;
        }

        return $rendered;
    }
}
