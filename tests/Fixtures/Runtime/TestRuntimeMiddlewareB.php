<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

final class TestRuntimeMiddlewareB implements RuntimeMiddleware
{
    public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
    {
        TestRuntimeMiddlewareA::$log[] = 'B';

        return $next($request);
    }
}
