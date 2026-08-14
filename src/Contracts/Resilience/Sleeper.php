<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

interface Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void;
}
