<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

/**
 * @internal
 */
enum PromptTemplateTokenType
{
    case Literal;
    case Variable;
}
