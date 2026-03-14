<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use Throwable;

final class SynchronousPipelineRunner implements PipelineRunner
{
    public function run(Pipeline $pipeline, RunContext $context): RunContext
    {
        $currentContext = $context;

        foreach ($pipeline->steps() as $step) {
            try {
                $currentContext = $step->handle($currentContext);
            } catch (Throwable $throwable) {
                throw PipelineExecutionException::forStep($step::class, $throwable);
            }
        }

        return $currentContext;
    }
}
