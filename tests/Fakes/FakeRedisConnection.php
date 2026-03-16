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
     * @param list<mixed> $arguments
     */
    public function command(string $name, array $arguments): mixed
    {
        return match (strtoupper($name)) {
            'GET' => $this->get((string)($arguments[0] ?? '')),
            'SET' => $this->set(
                (string)($arguments[0] ?? ''),
                (string)($arguments[1] ?? ''),
            ),
            'DEL' => $this->del((string)($arguments[0] ?? '')),
            'KEYS' => $this->keys((string)($arguments[0] ?? '')),
            default => throw new RuntimeException("Unsupported fake redis command [{$name}]."),
        };
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, string $value): string
    {
        $this->values[$key] = $value;

        return 'OK';
    }

    public function del(string $key): int
    {
        if (!array_key_exists($key, $this->values)) {
            return 0;
        }

        unset($this->values[$key]);

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
}
