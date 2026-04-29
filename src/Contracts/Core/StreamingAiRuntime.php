<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamChunk;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure;
use Generator;

/**
 * Stream-oriented execution for text generation (ordered chunks, then terminal complete or failure).
 */
interface StreamingAiRuntime
{
    /**
     * @return Generator<int, StreamChunk|StreamComplete|StreamFailure>
     */
    public function executeStream(ExecutionRequest $request): Generator;
}
