<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;

final readonly class PromptTemplate
{
    private ParsedPromptTemplate $parsedTemplate;

    /**
     * @param list<string> $variables
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $content,
        public array $variables = [],
    ) {
        $this->parsedTemplate = (new PromptTemplateParser())->parse($content);
    }

    public static function fromContent(string $name, string $version, string $content): self
    {
        $parsedTemplate = (new PromptTemplateParser())->parse($content);

        return new self(
            name: $name,
            version: $version,
            content: $content,
            variables: $parsedTemplate->variables,
        );
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(array $variables = []): string
    {
        $missingVariables = [];

        foreach ($this->variables as $variable) {
            if (!array_key_exists($variable, $variables)) {
                $missingVariables[] = $variable;
            }
        }

        if ($missingVariables !== []) {
            throw MissingPromptVariableException::forTemplate($this->name, $this->version, $missingVariables);
        }

        return $this->parsedTemplate->render($variables);
    }
}
