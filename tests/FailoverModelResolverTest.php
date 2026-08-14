<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\FailoverModelPolicy;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\FailoverModelResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ResolvedProviderTarget;

it('applies explicit request models only to the initial profile by default', function (): void {
    $resolver = new FailoverModelResolver(FailoverModelPolicy::InitialOnly);

    expect($resolver->requestModelForFallback(
        'gpt-explicit',
        resolvedFailoverModelTarget('openai'),
        failoverModelDefinition('secondary', 'anthropic'),
    ))->toBeNull();
});

it('preserves explicit models only between profiles using the same sdk provider when configured', function (): void {
    $resolver = new FailoverModelResolver(FailoverModelPolicy::PreserveWhenSameSdkProvider);

    expect($resolver->requestModelForFallback(
        'gpt-explicit',
        resolvedFailoverModelTarget('openai'),
        failoverModelDefinition('same-provider', 'openai'),
    ))->toBe('gpt-explicit')
        ->and($resolver->requestModelForFallback(
            'gpt-explicit',
            resolvedFailoverModelTarget('openai'),
            failoverModelDefinition('cross-provider', 'anthropic'),
        ))->toBeNull();
});

it('preserves explicit models across all fallback profiles only in legacy mode', function (): void {
    $resolver = new FailoverModelResolver(FailoverModelPolicy::PreserveAlwaysLegacy);

    expect($resolver->requestModelForFallback(
        'gpt-explicit',
        resolvedFailoverModelTarget('openai'),
        failoverModelDefinition('secondary', 'anthropic'),
    ))->toBe('gpt-explicit');
});

it('does not invent an explicit model when the request omits one', function (FailoverModelPolicy $policy): void {
    $resolver = new FailoverModelResolver($policy);

    expect($resolver->requestModelForFallback(
        null,
        resolvedFailoverModelTarget('openai'),
        failoverModelDefinition('secondary', 'openai'),
    ))->toBeNull();
})->with(FailoverModelPolicy::cases());

function resolvedFailoverModelTarget(string $sdkProvider): ResolvedProviderTarget
{
    return new ResolvedProviderTarget(
        profileName: 'primary',
        sdkProviderName: $sdkProvider,
        driver: $sdkProvider,
        model: 'gpt-explicit',
    );
}

function failoverModelDefinition(string $name, string $sdkProvider): ProviderDefinition
{
    return new ProviderDefinition(
        name: $name,
        driver: $sdkProvider,
        sdkProvider: $sdkProvider,
        options: ['model' => 'profile-model'],
    );
}
