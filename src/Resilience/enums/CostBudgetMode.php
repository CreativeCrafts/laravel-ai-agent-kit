<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience\enums;

enum CostBudgetMode: string
{
    case Strict = 'strict';
    case Advisory = 'advisory';
}
