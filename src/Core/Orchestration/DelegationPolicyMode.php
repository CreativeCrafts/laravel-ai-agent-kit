<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

enum DelegationPolicyMode: string
{
    case STATIC_ONLY = 'static_only';
    case DYNAMIC_WITH_ALLOWLIST = 'dynamic_with_allowlist';
    case DYNAMIC_FULL_REGISTRY = 'dynamic_full_registry';
}
