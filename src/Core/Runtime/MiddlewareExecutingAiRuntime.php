<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\TerminatingRuntimeMiddleware;
use Throwable;

/**
 * Wraps an inner {@see AiRuntime} with an ordered stack of {@see RuntimeMiddleware}.
 */
final readonly class MiddlewareExecutingAiRuntime implements AiRuntime
{
    /**
     * @param list<RuntimeMiddleware> $middleware
     */
    public function __construct(
        private AiRuntime $inner,
        private array $middleware,
    ) {
    }

    public function execute(ExecutionRequest $request): ExecutionResult
    {
        $core = fn (ExecutionRequest $r): ExecutionResult => $this->inner->execute($r);

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function (Closure $next, RuntimeMiddleware $middleware): Closure {
                return static fn (ExecutionRequest $r): ExecutionResult => $middleware->handle($r, $next);
            },
            $core,
        );

        try {
            $result = $pipeline($request);
        } catch (Throwable $throwable) {
            throw $throwable;
        }

        foreach (array_reverse($this->middleware) as $middleware) {
            if ($middleware instanceof TerminatingRuntimeMiddleware) {
                $middleware->terminate($request, $result);
            }
        }

        return $result;
    }
}
