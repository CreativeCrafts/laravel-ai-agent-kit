<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

enum ToolKind: string
{
    case Custom = 'custom';
    case Provider = 'provider';
}
