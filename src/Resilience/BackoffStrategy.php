<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

enum BackoffStrategy: string
{
    case Constant = 'constant';
    case Linear = 'linear';
    case Exponential = 'exponential';
}
