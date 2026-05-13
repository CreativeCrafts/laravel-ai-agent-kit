<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

enum EvaluationImageInputKind: string
{
    case Url = 'url';
    case Base64 = 'base64';
    case Path = 'path';
    case Storage = 'storage';
    case Upload = 'upload';
}
