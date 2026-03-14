<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

interface ProviderSelector
{
    public function selectDefault(): ProviderDefinition;
}
