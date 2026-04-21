<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Support;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\NoCompatibleAgentProviderProfileException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use Throwable;

final class FailureCategoryResolver
{
    public static function forThrowable(Throwable $throwable): string
    {
        $current = $throwable;

        while ($current instanceof Throwable) {
            $resolved = self::resolveSingle($current);

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }

            $current = $current->getPrevious();
        }

        return FailureCategory::ExecutionFailed->value;
    }

    public static function logicalFailure(): string
    {
        return FailureCategory::LogicalFailure->value;
    }

    private static function resolveSingle(Throwable $throwable): ?string
    {
        return match (true) {
            $throwable instanceof HasFailureCategory => $throwable->failureCategory(),
            $throwable instanceof NoCompatibleAgentProviderProfileException => FailureCategory::ProviderProfileMismatch->value,
            $throwable instanceof ProviderDisabledException,
              $throwable instanceof ProviderNotInFailoverOrderException => FailureCategory::FailoverPolicyError->value,
            default => null,
        };
    }
}
