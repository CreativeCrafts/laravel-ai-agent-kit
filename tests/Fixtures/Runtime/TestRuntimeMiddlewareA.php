<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

final class TestRuntimeMiddlewareA implements RuntimeMiddleware
{
    /** @var list<string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
    {
        self::$log[] = 'A';

        return $next($request);
    }
}
