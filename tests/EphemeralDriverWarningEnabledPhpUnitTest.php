<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use Illuminate\Log\Events\MessageLogged;
use PHPUnit\Framework\Attributes\Test;

final class EphemeralDriverWarningEnabledPhpUnitTest extends EphemeralDriverWarningTestCase
{
    #[Test]
    public function it_logs_a_warning_when_in_memory_memory_driver_is_selected(): void
    {
        $warnings = array_values(array_filter(
            EphemeralDriverWarningLogCapture::$messages,
            static fn (MessageLogged $e): bool => $e->level === 'warning'
                && str_contains($e->message, 'in-memory')
                && isset($e->context['drivers'])
                && in_array('memory.default_driver=in_memory', $e->context['drivers'], true),
        ));

        $this->assertNotEmpty($warnings);
    }
}
