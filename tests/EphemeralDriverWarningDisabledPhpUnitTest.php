<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use Illuminate\Log\Events\MessageLogged;
use PHPUnit\Framework\Attributes\Test;

final class EphemeralDriverWarningDisabledPhpUnitTest extends EphemeralDriverWarningDisabledTestCase
{
    #[Test]
    public function it_does_not_log_ephemeral_warnings_when_disabled(): void
    {
        $warnings = array_values(array_filter(
            EphemeralDriverWarningLogCapture::$messages,
            static fn (MessageLogged $e): bool => $e->level === 'warning'
                && str_contains($e->message, 'laravel-ai-agent-kit: in-memory'),
        ));

        $this->assertSame([], $warnings);
    }
}
