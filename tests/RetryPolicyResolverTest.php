<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\RetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Resilience\BackoffStrategyConfig;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\BackoffStrategy;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RetryPolicy;

it('resolves a retry policy and bounds it by the configured retry budget', function () {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 2);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 5,
      'backoff' => [
        'strategy' => 'exponential',
        'base_delay_ms' => 100,
        'max_delay_ms' => 500,
        'multiplier' => 2.0,
      ],
    ]);

    /** @var RetryPolicyResolver $resolver */
    $resolver = app(RetryPolicyResolver::class);
    $policy = $resolver->resolve();

    expect($policy->enabled)
      ->toBeTrue()
      ->and($policy->maxAttempts)->toBe(3)
      ->and($policy->maxRetries())->toBe(2)
      ->and($policy->allowsRetryAfterAttempt(1))->toBeTrue()
      ->and($policy->allowsRetryAfterAttempt(2))->toBeTrue()
      ->and($policy->allowsRetryAfterAttempt(3))->toBeFalse()
      ->and($policy->delayForRetry(1))->toBe(100)
      ->and($policy->delayForRetry(2))->toBe(200)
      ->and($policy->delayForRetry(3))->toBe(0);
});

it('returns a non-retrying policy when retries are disabled', function () {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 2);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => false,
      'max_attempts' => 4,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 250,
        'max_delay_ms' => 250,
        'multiplier' => 2.0,
      ],
    ]);

    /** @var RetryPolicyResolver $resolver */
    $resolver = app(RetryPolicyResolver::class);
    $policy = $resolver->resolve();

    expect($policy->enabled)
      ->toBeFalse()
      ->and($policy->maxAttempts)->toBe(1)
      ->and($policy->maxRetries())->toBe(0)
      ->and($policy->allowsRetryAfterAttempt(1))->toBeFalse()
      ->and($policy->delayForRetry(1))->toBe(0);
});

it('evaluates linear and exponential backoff deterministically', function () {
    $linear = new BackoffStrategyConfig(
        strategy: BackoffStrategy::Linear,
        baseDelayMs: 100,
        maxDelayMs: 250,
        multiplier: 2.0,
    );

    $exponential = new BackoffStrategyConfig(
        strategy: BackoffStrategy::Exponential,
        baseDelayMs: 100,
        maxDelayMs: 500,
        multiplier: 2.0,
    );

    $policy = new RetryPolicy(
        enabled: true,
        maxAttempts: 4,
        backoff: $linear,
    );

    $bounded = $policy->boundedToMaxRetries(1);

    expect($linear->delayForRetry(1))
      ->toBe(100)
      ->and($linear->delayForRetry(2))->toBe(200)
      ->and($linear->delayForRetry(3))->toBe(250)
      ->and($exponential->delayForRetry(1))->toBe(100)
      ->and($exponential->delayForRetry(2))->toBe(200)
      ->and($exponential->delayForRetry(3))->toBe(400)
      ->and($bounded->enabled)->toBeTrue()
      ->and($bounded->maxAttempts)->toBe(2)
      ->and($bounded->maxRetries())->toBe(1)
      ->and($bounded->delayForRetry(1))->toBe(100)
      ->and($bounded->delayForRetry(2))->toBe(0);
});
