<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class TestPipelineResultHandler implements PipelineResultHandler
{
    /**
     * @var list<RunContext>
     */
    public static array $successes = [];

    /**
     * @var list<array{context: RunContext, throwable: Throwable}>
     */
    public static array $failures = [];

    public static function reset(): void
    {
        self::$successes = [];
        self::$failures = [];
    }

    public function handleSuccess(RunContext $context): void
    {
        self::$successes[] = $context;
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        self::$failures[] = [
          'context' => $context,
          'throwable' => $throwable,
        ];
    }
}
