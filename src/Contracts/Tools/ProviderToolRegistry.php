<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Tools;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ProviderToolNotRegisteredException;

/**
 * Registry of SDK-native provider tools (WebSearch, WebFetch, FileSearch, etc.).
 *
 * Distinct from the custom-tool registry: provider tools execute server-side
 * on the model provider, not locally in PHP. Factories are stored and invoked
 * on every `get()` to avoid shared-state leaks across calls.
 */
interface ProviderToolRegistry
{
    /**
     * @param Closure(): object $factory Closure returning a fresh SDK provider-tool instance.
     */
    public function register(string $name, Closure $factory): void;

    public function has(string $name): bool;

    /**
     * Invoke the factory registered under the given name and return a fresh instance.
     *
     * @throws ProviderToolNotRegisteredException
     */
    public function get(string $name): object;

    /**
     * @return list<string>
     */
    public function all(): array;
}
