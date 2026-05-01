<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

final readonly class StoreFileCountsDto
{
    public function __construct(
        public int $completed,
        public int $pending,
        public int $failed,
    ) {
    }
}
