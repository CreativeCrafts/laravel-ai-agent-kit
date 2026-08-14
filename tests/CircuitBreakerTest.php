<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\CacheCircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CircuitBreakerState;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

it('binds the circuit breaker manager contract to the in-memory implementation', function () {
    expect(app(CircuitBreakerManager::class))->toBeInstanceOf(InMemoryCircuitBreakerManager::class);
});

it('binds the cache circuit breaker driver when configured', function (): void {
    config()->set('ai-agent-kit.resilience.circuit_breaker.driver', 'cache');
    config()->set('ai-agent-kit.resilience.circuit_breaker.cache_store', 'array');
    forgetResolvedCircuitBreakerManagers();

    expect(app(CircuitBreakerManager::class))->toBeInstanceOf(CacheCircuitBreakerManager::class);
});

it('shares atomic cache-backed state across manager instances', function (): void {
    config()->set('ai-agent-kit.resilience.circuit_breaker', [
        'enabled' => true,
        'driver' => 'cache',
        'cache_store' => 'array',
        'key_prefix' => 'circuit-test:'.bin2hex(random_bytes(8)).':',
        'lock_seconds' => 2,
        'failure_threshold' => 2,
        'reset_timeout_seconds' => 60,
        'half_open_success_threshold' => 1,
    ]);

    /** @var CacheFactory $cache */
    $cache = app(CacheFactory::class);
    /** @var ConfigRepository $config */
    $config = app(ConfigRepository::class);

    $firstManager = new CacheCircuitBreakerManager($cache, $config);
    $secondManager = new CacheCircuitBreakerManager($cache, $config);
    $firstBreaker = $firstManager->for('providers.openai');
    $secondBreaker = $secondManager->for('providers.openai');
    $firstFailureAt = new DateTimeImmutable('2026-08-14T10:00:00+00:00');
    $secondFailureAt = $firstFailureAt->modify('+1 second');

    $firstBreaker->recordFailure($firstFailureAt);
    $sharedAfterFirstFailure = $secondBreaker->snapshot($firstFailureAt);
    $secondBreaker->recordFailure($secondFailureAt);
    $sharedAfterSecondFailure = $firstBreaker->snapshot($secondFailureAt);

    expect($sharedAfterFirstFailure->consecutiveFailures)->toBe(1)
        ->and($sharedAfterFirstFailure->state)->toBe(CircuitBreakerState::Closed)
        ->and($sharedAfterSecondFailure->consecutiveFailures)->toBe(2)
        ->and($sharedAfterSecondFailure->state)->toBe(CircuitBreakerState::Open)
        ->and($firstBreaker->allowsExecution($secondFailureAt))->toBeFalse()
        ->and($secondBreaker->allowsExecution($secondFailureAt))->toBeFalse();
});

it('coordinates half-open recovery through cache state', function (): void {
    config()->set('ai-agent-kit.resilience.circuit_breaker', [
        'enabled' => true,
        'driver' => 'cache',
        'cache_store' => 'array',
        'key_prefix' => 'circuit-recovery-test:'.bin2hex(random_bytes(8)).':',
        'lock_seconds' => 2,
        'failure_threshold' => 1,
        'reset_timeout_seconds' => 10,
        'half_open_success_threshold' => 2,
    ]);

    /** @var CacheFactory $cache */
    $cache = app(CacheFactory::class);
    /** @var ConfigRepository $config */
    $config = app(ConfigRepository::class);

    $firstManager = new CacheCircuitBreakerManager($cache, $config);
    $secondManager = new CacheCircuitBreakerManager($cache, $config);
    $firstBreaker = $firstManager->for('providers.anthropic');
    $secondBreaker = $secondManager->for('providers.anthropic');
    $openedAt = new DateTimeImmutable('2026-08-14T11:00:00+00:00');
    $halfOpenAt = $openedAt->modify('+11 seconds');

    $firstBreaker->recordFailure($openedAt);
    $firstSuccess = $secondBreaker->recordSuccess($halfOpenAt);
    $closed = $firstBreaker->recordSuccess($halfOpenAt->modify('+1 second'));

    expect($firstSuccess->state)->toBe(CircuitBreakerState::HalfOpen)
        ->and($firstSuccess->halfOpenSuccesses)->toBe(1)
        ->and($closed->state)->toBe(CircuitBreakerState::Closed)
        ->and($closed->consecutiveFailures)->toBe(0)
        ->and($closed->openedAt)->toBeNull();
});

it('opens after the configured failure threshold and transitions to half-open after the reset timeout', function () {
    config()->set('ai-agent-kit.resilience.circuit_breaker', [
      'enabled' => true,
      'failure_threshold' => 2,
      'reset_timeout_seconds' => 60,
      'half_open_success_threshold' => 2,
    ]);
    forgetResolvedCircuitBreakerManagers();

    /** @var CircuitBreakerManager $manager */
    $manager = app(CircuitBreakerManager::class);
    $breaker = $manager->for('providers.openai');

    $firstFailureAt = new DateTimeImmutable('2026-03-17T10:00:00+00:00');
    $secondFailureAt = new DateTimeImmutable('2026-03-17T10:00:05+00:00');
    $halfOpenAt = new DateTimeImmutable('2026-03-17T10:01:06+00:00');

    $firstSnapshot = $breaker->recordFailure($firstFailureAt);
    $secondSnapshot = $breaker->recordFailure($secondFailureAt);
    $halfOpenSnapshot = $breaker->snapshot($halfOpenAt);

    expect($firstSnapshot->state)
      ->toBe(CircuitBreakerState::Closed)
      ->and($firstSnapshot->consecutiveFailures)->toBe(1)
      ->and($secondSnapshot->state)->toBe(CircuitBreakerState::Open)
      ->and($secondSnapshot->consecutiveFailures)->toBe(2)
      ->and($breaker->allowsExecution($secondFailureAt))->toBeFalse()
      ->and($halfOpenSnapshot->state)->toBe(CircuitBreakerState::HalfOpen)
      ->and($breaker->allowsExecution($halfOpenAt))->toBeTrue();
});

it('closes after enough half-open successes and reopens immediately on a half-open failure', function () {
    config()->set('ai-agent-kit.resilience.circuit_breaker', [
      'enabled' => true,
      'failure_threshold' => 2,
      'reset_timeout_seconds' => 30,
      'half_open_success_threshold' => 2,
    ]);
    forgetResolvedCircuitBreakerManagers();

    /** @var CircuitBreakerManager $manager */
    $manager = app(CircuitBreakerManager::class);
    $breaker = $manager->for('providers.anthropic');

    $openedAt = new DateTimeImmutable('2026-03-17T11:00:00+00:00');
    $halfOpenAt = new DateTimeImmutable('2026-03-17T11:00:31+00:00');

    $breaker->recordFailure($openedAt);
    $breaker->recordFailure($openedAt->modify('+1 second'));

    $firstHalfOpenSuccess = $breaker->recordSuccess($halfOpenAt);
    $reopenedSnapshot = $breaker->recordFailure($halfOpenAt->modify('+1 second'));
    $recoveredHalfOpenAt = $halfOpenAt->modify('+32 seconds');

    $secondBreaker = $manager->for('providers.recovery');
    $secondBreaker->recordFailure($openedAt);
    $secondBreaker->recordFailure($openedAt->modify('+1 second'));
    $secondBreaker->recordSuccess($halfOpenAt);
    $closedSnapshot = $secondBreaker->recordSuccess($halfOpenAt->modify('+1 second'));

    expect($firstHalfOpenSuccess->state)
      ->toBe(CircuitBreakerState::HalfOpen)
      ->and($firstHalfOpenSuccess->halfOpenSuccesses)->toBe(1)
      ->and($reopenedSnapshot->state)->toBe(CircuitBreakerState::Open)
      ->and($reopenedSnapshot->consecutiveFailures)->toBe(2)
      ->and($breaker->snapshot($recoveredHalfOpenAt)->state)->toBe(CircuitBreakerState::HalfOpen)
      ->and($closedSnapshot->state)->toBe(CircuitBreakerState::Closed)
      ->and($closedSnapshot->consecutiveFailures)->toBe(0)
      ->and($closedSnapshot->halfOpenSuccesses)->toBe(0)
      ->and($closedSnapshot->openedAt)->toBeNull()
      ->and($closedSnapshot->lastFailureAt)->toBeNull();
});

it('stays closed when the circuit breaker is disabled', function () {
    config()->set('ai-agent-kit.resilience.circuit_breaker', [
      'enabled' => false,
      'failure_threshold' => 2,
      'reset_timeout_seconds' => 30,
      'half_open_success_threshold' => 1,
    ]);
    forgetResolvedCircuitBreakerManagers();

    /** @var CircuitBreakerManager $manager */
    $manager = app(CircuitBreakerManager::class);
    $breaker = $manager->for('providers.disabled');

    $snapshot = $breaker->recordFailure(new DateTimeImmutable('2026-03-17T12:00:00+00:00'));

    expect($snapshot->state)
      ->toBe(CircuitBreakerState::Closed)
      ->and($breaker->allowsExecution(new DateTimeImmutable('2026-03-17T12:05:00+00:00')))->toBeTrue();
});

function forgetResolvedCircuitBreakerManagers(): void
{
    app()->forgetInstance(CircuitBreakerManager::class);
    app()->forgetInstance(InMemoryCircuitBreakerManager::class);
    app()->forgetInstance(CacheCircuitBreakerManager::class);
}
