<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolNotRegisteredException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laravel\Ai\Contracts\Tool as SdkTool;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;

final readonly class SdkToolMaterializer
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ConfigRepository $config,
    ) {
    }

    /**
     * @param list<string> $toolNames
     * @return list<SdkTool|ProviderTool>
     */
    public function materialize(array $toolNames): array
    {
        $resolved = [];

        foreach ($this->normalizeToolNames($toolNames) as $toolName) {
            $resolved[] = $this->materializeTool($toolName);
        }

        return $resolved;
    }

    /**
     * @param list<string> $toolNames
     * @return list<string>
     */
    private function normalizeToolNames(array $toolNames): array
    {
        $resolved = [];

        foreach ($toolNames as $toolName) {
            if ($toolName === '') {
                continue;
            }
            if (in_array($toolName, $resolved, true)) {
                continue;
            }
            $resolved[] = $toolName;
        }

        return $resolved;
    }

    private function materializeTool(string $toolName): SdkTool|ProviderTool
    {
        if ($this->toolRegistry->has($toolName)) {
            return new SdkToolAdapter(
                tool: $this->toolRegistry->get($toolName),
                toolRegistry: $this->toolRegistry,
            );
        }

        $providerTool = $this->materializeProviderTool($toolName);

        if ($providerTool instanceof ProviderTool) {
            return $providerTool;
        }

        throw ToolNotRegisteredException::forName($toolName);
    }

    private function materializeProviderTool(string $toolName): ?ProviderTool
    {
        $configuration = $this->providerToolConfiguration($toolName);

        if ($configuration === null || ($configuration['enabled'] ?? true) !== true) {
            return null;
        }

        $type = $configuration['type'];

        return match ($type) {
            'web_search' => $this->createWebSearchTool($configuration),
            'web_fetch' => $this->createWebFetchTool($configuration),
            'file_search' => $this->createFileSearchTool($configuration),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function providerToolConfiguration(string $toolName): ?array
    {
        $tools = $this->config->get('ai-agent-kit.tools.provider_tools', []);

        if (!is_array($tools)) {
            return null;
        }

        $configuration = $tools[$toolName] ?? null;

        if (!is_array($configuration)) {
            return null;
        }

        /** @var array<string, mixed> $configuration */
        return $configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createWebSearchTool(array $configuration): WebSearch
    {
        $tool = new WebSearch(
            maxSearches: $this->nullableInt($configuration['max_searches'] ?? null),
            allowedDomains: $this->stringList($configuration['allowed_domains'] ?? []),
        );

        $location = $configuration['location'] ?? null;

        if (is_array($location)) {
            $tool->location(
                city: $this->nullableString($location['city'] ?? null),
                region: $this->nullableString($location['region'] ?? null),
                country: $this->nullableString($location['country'] ?? null),
            );
        }

        return $tool;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                $value,
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            ),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createWebFetchTool(array $configuration): WebFetch
    {
        return new WebFetch(
            maxSearches: $this->nullableInt($configuration['max_searches'] ?? null),
            allowedDomains: $this->stringList($configuration['allowed_domains'] ?? []),
        );
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createFileSearchTool(array $configuration): FileSearch
    {
        $filters = $configuration['filters'] ?? null;

        return new FileSearch(
            stores: $this->stringList($configuration['stores'] ?? []),
            where: is_array($filters) && $filters !== [] ? $filters : null,
        );
    }
}
