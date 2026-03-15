<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests;

use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKitServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'CreativeCrafts\\LaravelAiAgentKit\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('app.cipher', 'AES-256-CBC');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
          'driver' => 'sqlite',
          'database' => ':memory:',
          'prefix' => '',
          'foreign_key_constraints' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
          LaravelAiAgentKitServiceProvider::class,
        ];
    }
}
