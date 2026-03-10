<?php

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Commands\LaravelAiAgentKitCommand;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAiAgentKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ai-agent-kit')
            ->hasConfigFile('ai-agent-kit')
            ->hasViews()
            ->hasMigration('create_laravel_ai_agent_kit_table')
            ->hasCommand(LaravelAiAgentKitCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ConfigValidator::class, function (Application $app): ConfigValidator {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfigValidator($config);
        });
    }

    /**
     * @throws BindingResolutionException
     */
    public function packageBooted(): void
    {
        $app = $this->app;

        /** @var ConfigRepository $config */
        $config = $app->make(ConfigRepository::class);

        /** @var array{enabled?:bool}|null $validation */
        $validation = $config->get('ai-agent-kit.validation');

        $enabled = ! is_array($validation) || (bool) ($validation['enabled'] ?? true);

        if ($enabled) {
            $app->make(ConfigValidator::class)->validateCurrentConfig();
        }
    }
}
