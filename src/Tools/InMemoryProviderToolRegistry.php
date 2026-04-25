<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ProviderToolNotRegisteredException;
use InvalidArgumentException;

final class InMemoryProviderToolRegistry implements ProviderToolRegistry
{
    /**
     * @var array<string, Closure(): object>
     */
    private array $factories = [];

    public function register(string $name, Closure $factory): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Provider tool name must be a non-empty string.');
        }

        $this->factories[$name] = $factory;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->factories);
    }

    public function get(string $name): object
    {
        if (!$this->has($name)) {
            throw ProviderToolNotRegisteredException::forName($name);
        }

        return ($this->factories[$name])();
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->factories);
    }
}
