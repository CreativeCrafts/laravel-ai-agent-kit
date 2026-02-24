<?php

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Commands\LaravelAiAgentKitCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAiAgentKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ai-agent-kit')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_ai_agent_kit_table')
            ->hasCommand(LaravelAiAgentKitCommand::class);
    }
}
