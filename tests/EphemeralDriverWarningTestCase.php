<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use Illuminate\Log\Events\MessageLogged;

/**
 * Boots the package with ephemeral-driver warnings enabled for the testing environment.
 */
class EphemeralDriverWarningTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        EphemeralDriverWarningLogCapture::reset();

        $app['events']->listen(MessageLogged::class, EphemeralDriverWarningLogCapture::push(...));

        $app['config']->set('ai-agent-kit.ephemeral_driver_warnings', [
            'enabled' => true,
            'environments' => ['testing'],
        ]);
        $app['config']->set('ai-agent-kit.memory.default_driver', 'in_memory');
        $app['config']->set('ai-agent-kit.vector.default_driver', 'database');
    }
}
