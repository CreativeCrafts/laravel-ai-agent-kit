<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

enum MediaHostMatchMode: string
{
    case ExactOnly = 'exact_only';
    case ExactAndSubdomains = 'exact_and_subdomains';
}
