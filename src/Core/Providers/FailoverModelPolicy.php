<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

enum FailoverModelPolicy: string
{
    case InitialOnly = 'initial_only';
    case PreserveWhenSameSdkProvider = 'preserve_when_same_sdk_provider';
    case PreserveAlwaysLegacy = 'preserve_always_legacy';
}
