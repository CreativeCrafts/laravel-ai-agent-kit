<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

final class FakeRedisManager
{
    /**
     * @var array<string, FakeRedisConnection>
     */
    private array $connections = [];

    public function connection(?string $name = null): FakeRedisConnection
    {
        $resolvedName = $name ?? 'default';

        if (!array_key_exists($resolvedName, $this->connections)) {
            $this->connections[$resolvedName] = new FakeRedisConnection();
        }

        return $this->connections[$resolvedName];
    }
}
