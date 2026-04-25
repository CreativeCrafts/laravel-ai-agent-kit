<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use Laravel\Ai\Contracts\Tool as SdkTool;

final readonly class SdkToolMaterializer
{
    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {
    }

    /**
     * @param list<string> $toolNames
     * @return list<SdkTool>
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

    private function materializeTool(string $toolName): SdkTool
    {
        return new SdkToolAdapter(
            tool: $this->toolRegistry->get($toolName),
            toolRegistry: $this->toolRegistry,
        );
    }
}
