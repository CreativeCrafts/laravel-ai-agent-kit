<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Contracts;

interface HasFailureCategory
{
    public function failureCategory(): string;
}
