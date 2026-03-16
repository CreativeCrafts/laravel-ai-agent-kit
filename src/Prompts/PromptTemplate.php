<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;

final readonly class PromptTemplate
{
    /**
     * @param list<string> $variables
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $content,
        public array $variables = [],
    ) {
    }

    public static function fromContent(string $name, string $version, string $content): self
    {
        preg_match_all('/\{\{\s*([A-Za-z_]\w*)\s*}}/', $content, $matches);

        /** @var list<string> $variables */
        $variables = array_values(array_unique($matches[1]));

        return new self(
            name: $name,
            version: $version,
            content: $content,
            variables: $variables,
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

        return (string)preg_replace_callback(
            '/\{\{\s*([A-Za-z_]\w*)\s*}}/',
            static function (array $matches) use ($variables): string {
              $key = $matches[1];
              $value = $variables[$key] ?? null;

              return $value === null ? '' : (string)$value;
          },
            $this->content,
        );
    }
}
