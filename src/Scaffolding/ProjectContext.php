<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Scaffolding;

final readonly class ProjectContext
{
    /**
     * @param 'laravel_app'|'laravel_package'|'package'|'unknown' $projectType
     * @param array<string, string> $autoloadRoots
     */
    public function __construct(
        public string $basePath,
        public string $projectType,
        public bool $hasComposerJson,
        public bool $hasComposerLock,
        public ?string $rootNamespace,
        public ?string $laravelVersion,
        public bool $hasLaravelAiSdk,
        public string $sourceDirectory,
        public string $promptsDirectory,
        public array $autoloadRoots,
    ) {
    }

    public function toolsDirectory(): string
    {
        return $this->sourceDirectory . '/Tools';
    }

    public function agentsDirectory(): string
    {
        return $this->sourceDirectory . '/Agents';
    }

    public function pipelinesDirectory(): string
    {
        return $this->sourceDirectory . '/Pipelines';
    }

    public function toolsNamespace(): ?string
    {
        return $this->appendNamespace('Tools');
    }

    public function agentsNamespace(): ?string
    {
        return $this->appendNamespace('Agents');
    }

    public function pipelinesNamespace(): ?string
    {
        return $this->appendNamespace('Pipelines');
    }

    private function appendNamespace(string $segment): ?string
    {
        if ($this->rootNamespace === null || $this->rootNamespace === '') {
            return null;
        }

        return rtrim($this->rootNamespace, '\\') . '\\' . $segment;
    }
}
