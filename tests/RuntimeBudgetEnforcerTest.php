<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\CostEstimate;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use Illuminate\Config\Repository;

it('separates cost preflight from token and tool postflight checks', function (): void {
    $enforcer = new RuntimeBudgetEnforcer(new Repository([
      'ai-agent-kit' => [
        'budgets' => [
          'max_cost_usd' => 0.10,
          'cost_estimation_mode' => 'strict',
          'max_tokens' => 100,
          'max_tool_calls' => 2,
        ],
      ],
    ]));

    $enforcer->assertPreflight('budget-split', new CostEstimate(0.05, 'test_estimator'));
    $enforcer->assertPostflight('budget-split', totalTokens: 100, toolCallCount: 2);

    expect(true)->toBeTrue();
});

it('rejects an over-limit estimate during preflight', function (): void {
    $enforcer = new RuntimeBudgetEnforcer(new Repository([
      'ai-agent-kit' => [
        'budgets' => [
          'max_cost_usd' => 0.10,
          'cost_estimation_mode' => 'strict',
        ],
      ],
    ]));

    $enforcer->assertPreflight('budget-preflight-limit', new CostEstimate(0.11, 'test_estimator'));
})->throws(RuntimeBudgetExceededException::class, 'max_cost_usd [0.1]');

it('allows an unknown estimate only in advisory mode', function (): void {
    $enforcer = new RuntimeBudgetEnforcer(new Repository([
      'ai-agent-kit' => [
        'budgets' => [
          'max_cost_usd' => 0.10,
          'cost_estimation_mode' => 'advisory',
        ],
      ],
    ]));

    $enforcer->assertPreflight('budget-advisory', null);

    expect(true)->toBeTrue();
});
