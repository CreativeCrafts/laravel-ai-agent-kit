<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CircuitBreakerState;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;

it('binds the circuit breaker manager contract to the in-memory implementation', function () {
    expect(app(CircuitBreakerManager::class))->toBeInstanceOf(InMemoryCircuitBreakerManager::class);
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
}
