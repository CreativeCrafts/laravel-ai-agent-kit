<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\PromptNotFoundException;

final readonly class InMemoryPromptRepository implements PromptRepository
{
    /**
     * @var array<string, array<string, PromptTemplate>>
     */
    private array $templates;

    /**
     * @param array<string, array<string, string>> $templates
     */
    public function __construct(array $templates = [])
    {
        $resolvedTemplates = [];

        foreach ($templates as $name => $versions) {
            $resolvedVersions = [];

            foreach ($versions as $version => $content) {
                $resolvedVersions[$version] = PromptTemplate::fromContent(
                    name: $name,
                    version: $version,
                    content: $content,
                );
            }

            $resolvedTemplates[$name] = $resolvedVersions;
        }

        $this->templates = $resolvedTemplates;
    }

    public function has(string $name, ?string $version = null): bool
    {
        try {
            $this->get($name, $version);

            return true;
        } catch (PromptNotFoundException) {
            return false;
        }
    }

    public function get(string $name, ?string $version = null): PromptTemplate
    {
        $versions = $this->templates[$name] ?? null;

        if ($versions === null || $versions === []) {
            throw PromptNotFoundException::forName($name, $version);
        }

        if ($version !== null) {
            return $versions[$version] ?? throw PromptNotFoundException::forName($name, $version);
        }

        $resolvedVersion = $this->resolveLatestVersion(array_keys($versions));

        return $versions[$resolvedVersion] ?? throw PromptNotFoundException::forName($name);
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(string $name, array $variables = [], ?string $version = null): string
    {
        return $this->get($name, $version)->render($variables);
    }

    /**
     * @param list<string> $versions
     */
    private function resolveLatestVersion(array $versions): string
    {
        usort($versions, static function (string $left, string $right): int {
            return version_compare($right, $left);
        });

        return $versions[0];
    }
}
