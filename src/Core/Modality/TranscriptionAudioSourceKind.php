<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

enum TranscriptionAudioSourceKind: string
{
    case Base64 = 'base64';
    case Path = 'path';
    case Storage = 'storage';
    case Upload = 'upload';
    case Url = 'url';
}
