<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use DateTimeImmutable;

/**
 * Controls which persisted attachment payloads may be rehydrated for subsequent turns.
 */
final readonly class AttachmentReplayPolicy
{
    /**
     * @param list<string> $denyTypes Attachment `type` values excluded from replay (e.g. `base64-image`).
     * @param list<string> $denyUrlSubstrings If a replayable attachment has a `url` field, deny when any substring matches (case-insensitive).
     */
    public function __construct(
        public bool $enabled = false,
        public ?int $maxPerTurn = null,
        public ?int $maxAgeSeconds = null,
        public array $denyTypes = [
            'base64-image',
            'base64-document',
            'base64-audio',
            'local-image',
            'local-document',
            'local-audio',
        ],
        public bool $allowProviderReferences = false,
        public array $denyUrlSubstrings = [],
    ) {
    }

    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    /**
     * @param array<string, mixed> $payload Single serialized attachment (must include `type` string).
     */
    public function evaluate(array $payload, DateTimeImmutable $messageCreatedAt, int $index): AttachmentReplayDecision
    {
        if (!$this->enabled) {
            return AttachmentReplayDecision::excluded('replay_disabled');
        }

        $type = $payload['type'] ?? null;
        $typeString = is_string($type) ? $type : '';

        if ($typeString === '') {
            return AttachmentReplayDecision::excluded('missing_type');
        }

        if ($this->maxAgeSeconds !== null) {
            $age = max(0, time() - $messageCreatedAt->getTimestamp());
            if ($age > $this->maxAgeSeconds) {
                return AttachmentReplayDecision::excluded('expired');
            }
        }

        if (in_array($typeString, $this->denyTypes, true)) {
            return AttachmentReplayDecision::excluded('type_denied');
        }

        if (!$this->allowProviderReferences
            && str_starts_with($typeString, 'provider-')) {
            return AttachmentReplayDecision::excluded('provider_reference_not_allowed');
        }

        $url = $payload['url'] ?? null;
        if (is_string($url) && $url !== '' && $this->denyUrlSubstrings !== []) {
            $lower = strtolower($url);
            foreach ($this->denyUrlSubstrings as $fragment) {
                if ($fragment === '') {
                    continue;
                }

                if (str_contains($lower, strtolower($fragment))) {
                    return AttachmentReplayDecision::excluded('authorization_denied');
                }
            }
        }

        if ($this->maxPerTurn !== null && $index >= $this->maxPerTurn) {
            return AttachmentReplayDecision::excluded('per_turn_limit');
        }

        return AttachmentReplayDecision::allowed();
    }
}
