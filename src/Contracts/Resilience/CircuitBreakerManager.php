<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

interface CircuitBreakerManager
{
    public function for(string $key): CircuitBreaker;
}
