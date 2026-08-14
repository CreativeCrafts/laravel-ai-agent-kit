<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use RuntimeException;

final class FakeRedisConnection
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @var array<string, int>
     */
    private array $ttlSeconds = [];

    /**
     * @var list<array{name: string, arguments: list<mixed>}>
     */
    private array $commands = [];

    /**
     * @param list<mixed> $arguments
     */
    public function command(string $name, array $arguments): mixed
    {
        $name = strtoupper($name);
        $this->commands[] = ['name' => $name, 'arguments' => $arguments];

        return match ($name) {
            'PING' => 'PONG',
            'GET' => $this->get((string)($arguments[0] ?? '')),
            'SET' => $this->setCommand($arguments),
            'DEL' => $this->del((string)($arguments[0] ?? '')),
            'KEYS' => $this->keys((string)($arguments[0] ?? '')),
            'SCAN' => $this->scan($arguments),
            'EVAL' => $this->evaluateCompareAndSet($arguments),
            default => throw new RuntimeException("Unsupported fake redis command [{$name}]."),
        };
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): string
    {
        $this->values[$key] = $value;

        if ($ttlSeconds === null) {
            unset($this->ttlSeconds[$key]);
        } else {
            $this->ttlSeconds[$key] = $ttlSeconds;
        }

        return 'OK';
    }

    public function ttlFor(string $key): ?int
    {
        return $this->ttlSeconds[$key] ?? null;
    }

    /**
     * @return list<array{name: string, arguments: list<mixed>}>
     */
    public function recordedCommands(): array
    {
        return $this->commands;
    }

    public function del(string $key): int
    {
        if (!array_key_exists($key, $this->values)) {
            return 0;
        }

        unset($this->values[$key], $this->ttlSeconds[$key]);

        return 1;
    }

    /**
     * @return list<string>
     */
    public function keys(string $pattern): array
    {
        if (!str_ends_with($pattern, '*')) {
            return array_key_exists($pattern, $this->values) ? [$pattern] : [];
        }

        $prefix = substr($pattern, 0, -1);
        $keys = [];

        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, $prefix)) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param list<mixed> $arguments
     * @return array{0: string, 1: list<string>}
     */
    private function scan(array $arguments): array
    {
        $pattern = '*';
        $matchIndex = array_search('MATCH', array_map(static fn ($value) => is_string($value) ? strtoupper($value) : $value, $arguments), true);

        if ($matchIndex !== false) {
            $candidate = $arguments[$matchIndex + 1] ?? '*';
            if (is_string($candidate) && $candidate !== '') {
                $pattern = $candidate;
            }
        }

        return ['0', $this->keys($pattern)];
    }

    /**
     * @param list<mixed> $arguments
     */
    private function setCommand(array $arguments): string
    {
        $key = (string)($arguments[0] ?? '');
        $value = (string)($arguments[1] ?? '');
        $ttlSeconds = null;

        if (($arguments[2] ?? null) === 'EX' && is_int($arguments[3] ?? null)) {
            $ttlSeconds = $arguments[3];
        }

        return $this->set($key, $value, $ttlSeconds);
    }

    /**
     * Emulates the package's atomic conversation compare-and-set Lua script.
     *
     * @param list<mixed> $arguments
     */
    private function evaluateCompareAndSet(array $arguments): int
    {
        $key = (string)($arguments[2] ?? '');
        $expectedRevision = (int)($arguments[3] ?? 0);
        $initialValue = (string)($arguments[4] ?? '');
        $updatedValue = (string)($arguments[5] ?? '');
        $ttl = $arguments[6] ?? null;
        $ttlSeconds = is_string($ttl) && ctype_digit($ttl) ? (int)$ttl : null;
        $current = $this->get($key);

        if ($current === null) {
            if ($expectedRevision !== 0) {
                return 0;
            }

            $this->set($key, $initialValue, $ttlSeconds);

            return 1;
        }

        $decoded = json_decode($current, true);

        if (!is_array($decoded)) {
            return -2;
        }

        $actualRevision = $decoded['revision'] ?? 0;

        if (!is_int($actualRevision) || $actualRevision !== $expectedRevision) {
            return 0;
        }

        $this->set($key, $updatedValue, $ttlSeconds);

        return 2;
    }
}
