<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use Illuminate\Log\Events\MessageLogged;

/**
 * Same as base TestCase but ephemeral warnings explicitly disabled (for negative assertion).
 */
class EphemeralDriverWarningDisabledTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        EphemeralDriverWarningLogCapture::reset();

        $app['events']->listen(MessageLogged::class, EphemeralDriverWarningLogCapture::push(...));

        $app['config']->set('ai-agent-kit.ephemeral_driver_warnings', [
            'enabled' => false,
            'environments' => ['testing'],
        ]);
        $app['config']->set('ai-agent-kit.memory.default_driver', 'in_memory');
    }
}
