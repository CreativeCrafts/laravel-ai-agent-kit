<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;

final class LaravelAiAgentKit
{
    public static function prompt(string $promptName): PromptBlueprint
    {
        return PromptBlueprint::forPrompt($promptName);
    }
}
