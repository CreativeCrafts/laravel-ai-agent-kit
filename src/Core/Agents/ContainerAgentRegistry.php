<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\AgentAlreadyRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\AgentNotRegisteredException;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions\InvalidAgentClassException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

final class ContainerAgentRegistry implements AgentRegistry
{
    /**
     * @var array<string, class-string<Agent>>
     */
    private array $agentClasses = [];

    /**
     * @var array<string, AgentDefinition>
     */
    private array $definitions = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public function registerMany(iterable $agentClasses): void
    {
        foreach ($agentClasses as $agentClass) {
            $this->register($agentClass);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    public function register(string $agentClass): void
    {
        $agent = $this->resolveAgent($agentClass);
        $definition = $agent->definition();
        $agentKey = $definition->key;

        if (array_key_exists($agentKey, $this->agentClasses)) {
            throw AgentAlreadyRegisteredException::forKey(
                agentKey: $agentKey,
                existingClass: $this->agentClasses[$agentKey],
                newClass: $agentClass,
            );
        }

        $this->agentClasses[$agentKey] = $agentClass;
        $this->definitions[$agentKey] = $definition;
    }

    public function has(string $agentKey): bool
    {
        return array_key_exists($agentKey, $this->agentClasses);
    }

    /**
     * @throws BindingResolutionException
     */
    public function get(string $agentKey): Agent
    {
        $agentClass = $this->agentClasses[$agentKey] ?? null;

        if ($agentClass === null) {
            throw AgentNotRegisteredException::forKey($agentKey);
        }

        $agent = $this->resolveAgent($agentClass);
        $resolvedKey = $agent->definition()->key;

        if ($resolvedKey !== $agentKey) {
            throw InvalidAgentClassException::forClass(
                agentClass: $agentClass,
                reason: sprintf(
                    'resolved agent key [%s] does not match registry key [%s].',
                    $resolvedKey,
                    $agentKey,
                ),
            );
        }

        return $agent;
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @param class-string<Agent> $agentClass
     * @throws BindingResolutionException
     */
    private function resolveAgent(string $agentClass): Agent
    {
        if (!class_exists($agentClass)) {
            throw InvalidAgentClassException::forClass($agentClass, 'class does not exist.');
        }

        $agent = $this->container->make($agentClass);

        if (!$agent instanceof Agent) {
            throw InvalidAgentClassException::forClass($agentClass, 'resolved instance must implement the agent contract.');
        }

        return $agent;
    }
}
