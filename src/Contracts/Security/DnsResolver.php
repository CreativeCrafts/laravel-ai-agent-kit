<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Security;

interface DnsResolver
{
    /**
     * Resolve all available IPv4 and IPv6 addresses for a host.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
