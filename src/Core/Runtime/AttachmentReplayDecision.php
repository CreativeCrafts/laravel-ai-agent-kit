<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

final readonly class AttachmentReplayDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $exclusionReason,
    ) {
    }

    public static function allowed(): self
    {
        return new self(true, null);
    }

    public static function excluded(string $reason): self
    {
        return new self(false, $reason);
    }
}
