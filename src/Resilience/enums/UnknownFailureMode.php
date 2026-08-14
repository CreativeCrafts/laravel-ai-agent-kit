<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience\enums;

enum UnknownFailureMode: string
{
    case Strict = 'strict';
    case LegacyFailover = 'legacy_failover';
}
