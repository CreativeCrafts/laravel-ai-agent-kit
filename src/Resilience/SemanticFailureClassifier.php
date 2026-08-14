<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\FailureClassifier;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\NoCompatibleAgentProviderProfileException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\ConversationContextBridgeException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationStoreException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\UnknownFailureMode;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class SemanticFailureClassifier implements FailureClassifier
{
    public function __construct(
        private UnknownFailureMode $unknownFailureMode = UnknownFailureMode::Strict,
    ) {
    }

    public function classify(Throwable $throwable): FailureDisposition
    {
        $current = $throwable;

        while ($current instanceof Throwable) {
            $disposition = $this->classifySingle($current);

            if ($disposition instanceof FailureDisposition) {
                return $disposition;
            }

            $current = $current->getPrevious();
        }

        if ($this->unknownFailureMode === UnknownFailureMode::LegacyFailover) {
            return new FailureDisposition(
                category: FailureCategory::ProviderFailure,
                providerHealthFailure: true,
                retryable: true,
                failoverSafe: true,
                reason: 'unknown_legacy_failover',
            );
        }

        return new FailureDisposition(
            category: FailureCategory::ExecutionFailed,
            providerHealthFailure: false,
            retryable: false,
            failoverSafe: false,
            reason: 'unknown_fail_closed',
        );
    }

    private function classifySingle(Throwable $throwable): ?FailureDisposition
    {
        if ($throwable instanceof RuntimeExecutionException) {
            return $throwable->failureCategory() === FailureCategory::ExecutionFailed->value
                ? null
                : $this->forKnownCategory(FailureCategory::tryFrom($throwable->failureCategory()));
        }

        if ($throwable instanceof RateLimitedException) {
            return new FailureDisposition(FailureCategory::RateLimited, false, true, true, 'provider_rate_limited');
        }

        if ($throwable instanceof ProviderOverloadedException) {
            return new FailureDisposition(FailureCategory::ProviderOverloaded, true, true, true, 'provider_overloaded');
        }

        if ($throwable instanceof InsufficientCreditsException) {
            return new FailureDisposition(FailureCategory::QuotaExceeded, false, false, true, 'provider_quota_exceeded');
        }

        if ($throwable instanceof ConnectionException || $throwable instanceof ConnectException) {
            return new FailureDisposition(FailureCategory::ProviderTransport, true, true, true, 'provider_connection_failed');
        }

        if ($throwable instanceof RequestException) {
            return $this->forHttpStatus($throwable->response->status());
        }

        if ($throwable instanceof GuzzleRequestException) {
            $response = $throwable->getResponse();

            return $response instanceof ResponseInterface
                ? $this->forHttpStatus($response->getStatusCode())
                : new FailureDisposition(FailureCategory::ProviderTransport, true, true, true, 'provider_transport_failed');
        }

        if ($throwable instanceof RuntimeBudgetExceededException) {
            return new FailureDisposition(FailureCategory::BudgetExceeded, false, false, false, 'runtime_budget_exceeded');
        }

        if ($throwable instanceof ToolAuthorizationDeniedException) {
            return new FailureDisposition(FailureCategory::ToolAuthorizationDenied, false, false, false, 'tool_authorization_denied');
        }

        if ($throwable instanceof ConversationContextBridgeException || $throwable instanceof ConversationStoreException) {
            return new FailureDisposition(FailureCategory::ConversationFailure, false, false, false, 'conversation_failure');
        }

        if (
            $throwable instanceof InvalidConfigurationException
            || $throwable instanceof BindingResolutionException
            || $throwable instanceof ProviderDisabledException
            || $throwable instanceof ProviderNotDefinedException
            || $throwable instanceof ProviderNotInFailoverOrderException
        ) {
            return new FailureDisposition(FailureCategory::ConfigurationFailure, false, false, false, 'local_configuration_failure');
        }

        if ($throwable instanceof InvalidArgumentException) {
            return new FailureDisposition(FailureCategory::InvalidRequest, false, false, false, 'invalid_request');
        }

        if ($throwable instanceof NoCompatibleAgentProviderProfileException || $throwable instanceof LogicException) {
            return new FailureDisposition(FailureCategory::UnsupportedCapability, false, false, false, 'unsupported_capability');
        }

        if ($throwable instanceof HasFailureCategory) {
            return $this->forKnownCategory(FailureCategory::tryFrom($throwable->failureCategory()));
        }

        return null;
    }

    private function forHttpStatus(int $status): FailureDisposition
    {
        return match (true) {
            $status === 401 || $status === 403 => new FailureDisposition(
                FailureCategory::AuthenticationFailed,
                false,
                false,
                true,
                'provider_authentication_failed',
            ),
            $status === 402 => new FailureDisposition(
                FailureCategory::QuotaExceeded,
                false,
                false,
                true,
                'provider_quota_exceeded',
            ),
            $status === 429 => new FailureDisposition(
                FailureCategory::RateLimited,
                false,
                true,
                true,
                'provider_rate_limited',
            ),
            $status === 408 || $status === 425 || $status >= 500 => new FailureDisposition(
                FailureCategory::ProviderOverloaded,
                true,
                true,
                true,
                'provider_transient_http_failure',
            ),
            default => new FailureDisposition(
                FailureCategory::InvalidRequest,
                false,
                false,
                false,
                'provider_request_rejected',
            ),
        };
    }

    private function forKnownCategory(?FailureCategory $category): ?FailureDisposition
    {
        if (!$category instanceof FailureCategory) {
            return null;
        }

        return match ($category) {
            FailureCategory::ProviderFailure,
            FailureCategory::ProviderTransport,
            FailureCategory::ProviderOverloaded => new FailureDisposition($category, true, true, true, 'categorized_provider_failure'),
            FailureCategory::RateLimited => new FailureDisposition($category, false, true, true, 'categorized_rate_limit'),
            FailureCategory::AuthenticationFailed,
            FailureCategory::QuotaExceeded => new FailureDisposition($category, false, false, true, 'categorized_provider_access_failure'),
            default => new FailureDisposition($category, false, false, false, 'categorized_non_retryable_failure'),
        };
    }
}
