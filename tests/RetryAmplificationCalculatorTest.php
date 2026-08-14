<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Resilience\RetryAmplificationCalculator;

it('calculates the worst case provider attempt amplification across all retry layers', function (): void {
    $estimate = (new RetryAmplificationCalculator())->estimate(
        queueAttempts: 3,
        pipelineStepAttempts: 2,
        providerAttemptsPerExecution: 4,
    );

    expect($estimate->worstCaseProviderAttempts)->toBe(24)
        ->and($estimate->isComplete())->toBeTrue();
});

it('reports an incomplete estimate when Laravel worker queue attempts are not explicit', function (): void {
    $estimate = (new RetryAmplificationCalculator())->estimate(
        queueAttempts: null,
        pipelineStepAttempts: 2,
        providerAttemptsPerExecution: 4,
    );

    expect($estimate->worstCaseProviderAttempts)->toBeNull()
        ->and($estimate->isComplete())->toBeFalse();
});
