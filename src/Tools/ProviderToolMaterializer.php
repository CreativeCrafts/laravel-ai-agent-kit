<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use InvalidArgumentException;
use Laravel\Ai\Providers\Tools\ProviderTool;

final readonly class ProviderToolMaterializer
{
    public function __construct(
        private ProviderToolRegistry $providerToolRegistry,
        private ToolAuthorizer $authorizer,
    ) {
    }

    /**
     * @param list<string> $providerToolNames
     * @return list<ProviderTool>
     */
    public function materialize(array $providerToolNames): array
    {
        $resolved = [];

        foreach ($this->normalizeToolNames($providerToolNames) as $toolName) {
            if (!$this->authorizer->authorizeProviderTool($toolName)) {
                throw ToolAuthorizationDeniedException::forProviderTool($toolName);
            }

            $tool = $this->providerToolRegistry->get($toolName);

            if (!$tool instanceof ProviderTool) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Provider tool factory registered under [%s] must return an instance of [%s]; got [%s].',
                        $toolName,
                        ProviderTool::class,
                        get_debug_type($tool),
                    ),
                );
            }

            $resolved[] = $tool;
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
}
