<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\Sleeper;
use InvalidArgumentException;

final readonly class SystemSleeper implements Sleeper
{
    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException('Sleep duration must be greater than or equal to zero milliseconds.');
        }

        if ($milliseconds === 0) {
            return;
        }

        usleep($milliseconds * 1000);
    }
}
