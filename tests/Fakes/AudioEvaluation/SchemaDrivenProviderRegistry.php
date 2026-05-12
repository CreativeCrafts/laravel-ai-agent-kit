<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use RuntimeException;

final class SchemaDrivenProviderRegistry implements ProviderRegistry
{
    public function has(string $providerName): bool
    {
        return $providerName === 'openai';
    }

    public function get(string $providerName): ProviderDefinition
    {
        if ($providerName !== 'openai') {
            throw new RuntimeException('Unknown provider.');
        }

        return new ProviderDefinition(
            name: 'openai',
            driver: 'openai',
            enabled: true,
            capabilities: ['text_generation', 'structured_output'],
        );
    }

    /** @return array<string, ProviderDefinition> */
    public function all(): array
    {
        return [
            'openai' => $this->get('openai'),
        ];
    }
}
