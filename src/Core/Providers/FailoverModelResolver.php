<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

final readonly class FailoverModelResolver
{
    public function __construct(
        private FailoverModelPolicy $policy,
    ) {
    }

    public function requestModelForFallback(
        ?string $requestModel,
        ResolvedProviderTarget $currentTarget,
        ProviderDefinition $fallbackDefinition,
    ): ?string {
        if ($requestModel === null) {
            return null;
        }

        return match ($this->policy) {
            FailoverModelPolicy::InitialOnly => null,
            FailoverModelPolicy::PreserveWhenSameSdkProvider =>
                $currentTarget->sdkProviderName === $fallbackDefinition->sdkProviderName()
                    ? $requestModel
                    : null,
            FailoverModelPolicy::PreserveAlwaysLegacy => $requestModel,
        };
    }
}
