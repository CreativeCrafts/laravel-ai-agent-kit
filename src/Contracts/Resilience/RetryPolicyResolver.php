<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\RetryPolicy;

interface RetryPolicyResolver
{
    /**
     * Resolve the effective retry policy that pipeline runners should honor.
     */
    public function resolve(): RetryPolicy;
}
