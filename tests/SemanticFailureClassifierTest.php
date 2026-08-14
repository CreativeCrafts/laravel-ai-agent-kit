<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Resilience\SemanticFailureClassifier;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\UnknownFailureMode;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

it('classifies installed laravel ai failover exceptions semantically', function (
    Throwable $throwable,
    FailureCategory $category,
    bool $providerHealthFailure,
    bool $retryable,
    bool $failoverSafe,
) {
    $disposition = (new SemanticFailureClassifier())->classify($throwable);

    expect($disposition->category)->toBe($category)
        ->and($disposition->providerHealthFailure)->toBe($providerHealthFailure)
        ->and($disposition->retryable)->toBe($retryable)
        ->and($disposition->failoverSafe)->toBe($failoverSafe)
        ->and($disposition->reason)->not->toBe('');
})->with([
  'rate limit' => [
    RateLimitedException::forProvider('openai', 429),
    FailureCategory::RateLimited,
    false,
    true,
    true,
  ],
  'provider overload' => [
    ProviderOverloadedException::forProvider('openai', 503),
    FailureCategory::ProviderOverloaded,
    true,
    true,
    true,
  ],
  'insufficient credits' => [
    InsufficientCreditsException::forProvider('openai', 402),
    FailureCategory::QuotaExceeded,
    false,
    false,
    true,
  ],
  'connection failure' => [
    new ConnectionException('connection failed'),
    FailureCategory::ProviderTransport,
    true,
    true,
    true,
  ],
  'invalid request' => [
    new InvalidArgumentException('invalid option'),
    FailureCategory::InvalidRequest,
    false,
    false,
    false,
  ],
]);

it('classifies supported laravel http response status families', function (
    int $status,
    FailureCategory $category,
    bool $providerHealthFailure,
    bool $retryable,
    bool $failoverSafe,
) {
    $throwable = new RequestException(new Response(new PsrResponse($status)));
    $disposition = (new SemanticFailureClassifier())->classify($throwable);

    expect($disposition->category)->toBe($category)
        ->and($disposition->providerHealthFailure)->toBe($providerHealthFailure)
        ->and($disposition->retryable)->toBe($retryable)
        ->and($disposition->failoverSafe)->toBe($failoverSafe);
})->with([
  'authentication' => [401, FailureCategory::AuthenticationFailed, false, false, true],
  'authorization' => [403, FailureCategory::AuthenticationFailed, false, false, true],
  'quota' => [402, FailureCategory::QuotaExceeded, false, false, true],
  'rate limit' => [429, FailureCategory::RateLimited, false, true, true],
  'request timeout' => [408, FailureCategory::ProviderOverloaded, true, true, true],
  'server failure' => [500, FailureCategory::ProviderOverloaded, true, true, true],
  'invalid request' => [422, FailureCategory::InvalidRequest, false, false, false],
]);

it('walks previous exception chains before treating a failure as unknown', function () {
    $throwable = new RuntimeException(
        'outer wrapper',
        previous: RateLimitedException::forProvider('openai', 429),
    );

    $disposition = (new SemanticFailureClassifier())->classify($throwable);

    expect($disposition->category)->toBe(FailureCategory::RateLimited)
        ->and($disposition->retryable)->toBeTrue()
        ->and($disposition->failoverSafe)->toBeTrue();
});

it('fails unknown throwables closed by default', function () {
    $disposition = (new SemanticFailureClassifier())->classify(new RuntimeException('unknown'));

    expect($disposition->category)->toBe(FailureCategory::ExecutionFailed)
        ->and($disposition->providerHealthFailure)->toBeFalse()
        ->and($disposition->retryable)->toBeFalse()
        ->and($disposition->failoverSafe)->toBeFalse()
        ->and($disposition->reason)->toBe('unknown_fail_closed');
});

it('offers an explicit legacy mode for broad unknown failure failover', function () {
    $disposition = (new SemanticFailureClassifier(UnknownFailureMode::LegacyFailover))
        ->classify(new RuntimeException('legacy unknown'));

    expect($disposition->category)->toBe(FailureCategory::ProviderFailure)
        ->and($disposition->providerHealthFailure)->toBeTrue()
        ->and($disposition->retryable)->toBeTrue()
        ->and($disposition->failoverSafe)->toBeTrue()
        ->and($disposition->reason)->toBe('unknown_legacy_failover');
});
