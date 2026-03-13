<?php

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
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
            ->hasMigration('create_laravel_ai_agent_kit_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ConfigValidator::class, function (Application $app): ConfigValidator {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfigValidator($config);
        });

        $this->app->singleton(ConfiguredProviderRegistry::class, function (Application $app): ConfiguredProviderRegistry {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfiguredProviderRegistry($config);
        });

        $this->app->singleton(ProviderRegistry::class, function (Application $app): ProviderRegistry {
            return $app->make(ConfiguredProviderRegistry::class);
        });

        $this->app->singleton(DefaultProviderSelector::class, function (Application $app): DefaultProviderSelector {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new DefaultProviderSelector(
                config: $config,
                providerRegistry: $app->make(ProviderRegistry::class),
            );
        });

        $this->app->singleton(ProviderSelector::class, function (Application $app): ProviderSelector {
            return $app->make(DefaultProviderSelector::class);
        });

        $this->app->singleton(ConfiguredFailoverProviderSelector::class, function (Application $app): ConfiguredFailoverProviderSelector {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfiguredFailoverProviderSelector(
                config: $config,
                providerRegistry: $app->make(ProviderRegistry::class),
            );
        });

        $this->app->singleton(FailoverProviderSelector::class, function (Application $app): FailoverProviderSelector {
            return $app->make(ConfiguredFailoverProviderSelector::class);
        });

        $this->app->singleton(SynchronousPipelineRunner::class, function (): SynchronousPipelineRunner {
            return new SynchronousPipelineRunner;
        });

        $this->app->singleton(PipelineRunner::class, function (Application $app): PipelineRunner {
            return $app->make(SynchronousPipelineRunner::class);
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
