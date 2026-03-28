<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\NoCompatibleAgentProviderProfileException;

beforeEach(function (): void {
    config()->set('ai-agent-kit.providers', [
      'anthropic-support' => [
        'driver' => 'anthropic',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-support' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
      'openai-refund' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['structured_output'],
        'options' => [],
      ],
    ]);

    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(ConfiguredAgentProviderProfileSelector::class);
    app()->forgetInstance(AgentProviderProfileSelector::class);
});

it('binds the agent provider profile selector through the container', function () {
    expect(app(AgentProviderProfileSelector::class))
      ->toBeInstanceOf(ConfiguredAgentProviderProfileSelector::class);
});

it('selects the primary compatible provider profile for an agent', function () {
    $provider = app(AgentProviderProfileSelector::class)->selectForAgent(
        new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
            fallbackProviderProfiles: ['openai-support'],
        ),
    );

    expect($provider->name)
      ->toBe('anthropic-support')
      ->and($provider->capabilities)->toBe(['text_generation']);
});

it('falls back to the next declared compatible provider profile when the primary profile is disabled', function () {
    config()->set('ai-agent-kit.providers.anthropic-support.enabled', false);
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(ConfiguredAgentProviderProfileSelector::class);
    app()->forgetInstance(AgentProviderProfileSelector::class);

    $provider = app(AgentProviderProfileSelector::class)->selectForAgent(
        new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
            fallbackProviderProfiles: ['openai-support'],
        ),
    );

    expect($provider->name)->toBe('openai-support');
});

it('throws when no declared provider profile satisfies the agent capability requirements', function () {
    try {
        app(AgentProviderProfileSelector::class)->selectForAgent(
            new AgentDefinition(
                key: 'refund.agent',
                displayName: 'Refund Agent',
                requiredCapabilities: ['vision_input'],
                primaryProviderProfile: 'openai-refund',
                fallbackProviderProfiles: ['openai-support'],
            ),
        );

        throw new RuntimeException('Expected the provider profile selector to throw an incompatibility exception.');
    } catch (NoCompatibleAgentProviderProfileException $exception) {
        expect($exception->getMessage())
          ->toBe('Agent [refund.agent] does not have a compatible configured provider profile.')
          ->and($exception->attempts())
          ->toBe([
            'profile [openai-refund] is missing capabilities [vision_input]',
            'profile [openai-support] is missing capabilities [vision_input]',
          ]);
    }
});

it('falls back to the next profile when the primary profile references an undefined provider', function () {
    $provider = app(AgentProviderProfileSelector::class)->selectForAgent(
        new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'non-existent-provider',
            fallbackProviderProfiles: ['openai-support'],
        ),
    );

    expect($provider->name)->toBe('openai-support');
});

it('selects the primary provider profile when the agent has no required capabilities', function () {
    $provider = app(AgentProviderProfileSelector::class)->selectForAgent(
        new AgentDefinition(
            key: 'generic.agent',
            displayName: 'Generic Agent',
            requiredCapabilities: [],
            primaryProviderProfile: 'anthropic-support',
            fallbackProviderProfiles: ['openai-support'],
        ),
    );

    expect($provider->name)->toBe('anthropic-support');
});
