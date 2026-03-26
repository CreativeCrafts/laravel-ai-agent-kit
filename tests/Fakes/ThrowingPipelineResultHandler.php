<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use RuntimeException;
use Throwable;

final class ThrowingPipelineResultHandler implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        throw new RuntimeException('Result handler failed while processing pipeline failure.');
    }
}
