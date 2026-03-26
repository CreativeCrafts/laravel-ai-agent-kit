<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\ContainerAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\AgentAlreadyRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\AgentNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\InvalidAgentClassException;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\AgentRegistryTestDependency;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\AgentRegistryTestDuplicateSupportAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\AgentRegistryTestInvalidClass;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\AgentRegistryTestSupportAgent;

it('binds the package agent registry through the container', function () {
    expect(app(AgentRegistry::class))
      ->toBeInstanceOf(ContainerAgentRegistry::class);
});

it('registers and resolves first class php agents via the laravel container', function () {
    app()->instance(AgentRegistryTestDependency::class, new AgentRegistryTestDependency('resolved-from-container'));

    $registry = app(AgentRegistry::class);
    $registry->register(AgentRegistryTestSupportAgent::class);

    expect($registry->has('support.agent'))
      ->toBeTrue()
      ->and($registry->all())->toHaveKey('support.agent')
      ->and($registry->all()['support.agent']->displayName)->toBe('Support Agent');

    $agent = $registry->get('support.agent');

    $result = $agent->handle(
        new AgentExecutionContext(
            orchestrationId: 'orch-registry-001',
            executionId: 'exec-registry-001',
            parentExecutionId: null,
            agent: $agent->definition(),
            providerProfile: 'anthropic-support',
            task: 'Resolve support workflow',
        ),
    );

    expect($agent)
      ->toBeInstanceOf(AgentRegistryTestSupportAgent::class)
      ->and($result->output)->toBe([
        'dependency' => 'resolved-from-container',
        'task' => 'Resolve support workflow',
      ]);
});

it('registers multiple agent classes deterministically', function () {
    app()->instance(AgentRegistryTestDependency::class, new AgentRegistryTestDependency('batch'));

    $registry = app(AgentRegistry::class);
    $registry->registerMany([
      AgentRegistryTestSupportAgent::class,
    ]);

    expect(array_keys($registry->all()))->toBe(['support.agent']);
});

it('fails with a typed exception when an agent key is registered twice', function () {
    app()->instance(AgentRegistryTestDependency::class, new AgentRegistryTestDependency('duplicate'));

    $registry = app(AgentRegistry::class);
    $registry->register(AgentRegistryTestSupportAgent::class);
    $registry->register(AgentRegistryTestDuplicateSupportAgent::class);
})->throws(AgentAlreadyRegisteredException::class, 'support.agent');

it('fails with a typed exception when an agent key is missing', function () {
    app(AgentRegistry::class)->get('missing.agent');
})->throws(AgentNotRegisteredException::class, 'missing.agent');

it('fails with a typed exception when an invalid agent class is registered', function () {
    app(AgentRegistry::class)->register(AgentRegistryTestInvalidClass::class);
})->throws(InvalidAgentClassException::class, 'must implement the agent contract');
