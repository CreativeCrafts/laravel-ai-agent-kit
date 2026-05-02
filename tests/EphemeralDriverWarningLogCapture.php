<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use Illuminate\Log\Events\MessageLogged;

/**
 * Captures {@see MessageLogged} for assertions (listener registered before app boots).
 */
final class EphemeralDriverWarningLogCapture
{
    /** @var list<MessageLogged> */
    public static array $messages = [];

    public static function reset(): void
    {
        self::$messages = [];
    }

    public static function push(MessageLogged $event): void
    {
        self::$messages[] = $event;
    }
}
