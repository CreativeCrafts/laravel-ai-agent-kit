<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\TerminatingRuntimeMiddleware;
use Generator;
use RuntimeException;

/**
 * Wraps an inner {@see AiRuntime} with an ordered stack of {@see RuntimeMiddleware}.
 */
final readonly class MiddlewareExecutingAiRuntime implements AiRuntime, StreamingAiRuntime
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

        $result = $pipeline($request);

        foreach (array_reverse($this->middleware) as $middleware) {
            if ($middleware instanceof TerminatingRuntimeMiddleware) {
                $middleware->terminate($request, $result);
            }
        }

        return $result;
    }

    public function executeStream(ExecutionRequest $request): Generator
    {
        if ($this->inner instanceof StreamingAiRuntime) {
            yield from $this->inner->executeStream($request);

            return;
        }

        throw new RuntimeException(
            sprintf('Inner %s does not implement %s.', $this->inner::class, StreamingAiRuntime::class),
        );
    }
}
