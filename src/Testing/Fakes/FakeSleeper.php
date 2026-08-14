<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\Sleeper;
use InvalidArgumentException;

final class FakeSleeper implements Sleeper
{
    /** @var list<int> */
    private array $recordedDelays = [];

    public function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException('Sleep duration must be greater than or equal to zero milliseconds.');
        }

        $this->recordedDelays[] = $milliseconds;
    }

    /**
     * @return list<int>
     */
    public function recordedDelays(): array
    {
        return $this->recordedDelays;
    }
}
