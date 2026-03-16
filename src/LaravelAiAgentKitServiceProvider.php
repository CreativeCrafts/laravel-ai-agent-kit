<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\LaravelQueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\NullConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Memory\StoreBackedConversationContextManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
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
          ->hasMigration('create_ai_agent_conversations_table')
          ->hasMigration('create_ai_agent_conversation_messages_table');
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

        $this->app->singleton(DatabaseConversationStore::class, function (Application $app): DatabaseConversationStore {
            /** @var DatabaseManager $database */
            $database = $app->make(DatabaseManager::class);
            /** @var Encrypter $encrypter */
            $encrypter = $app->make(Encrypter::class);
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new DatabaseConversationStore(
                database: $database,
                encrypter: $encrypter,
                connectionName: $this->nullableStringConfig($config, 'ai-agent-kit.memory.database.connection'),
                conversationsTable: $this->stringConfig($config, 'ai-agent-kit.memory.database.conversations_table'),
                messagesTable: $this->stringConfig($config, 'ai-agent-kit.memory.database.messages_table'),
                driverName: $this->stringConfig($config, 'ai-agent-kit.memory.database.driver_name'),
                retentionDays: $this->nullableIntConfig($config, 'ai-agent-kit.memory.database.retention_days'),
                encryptPayloads: (bool)$config->get('ai-agent-kit.memory.database.encrypt_payloads', true),
            );
        });

        $this->app->singleton(InMemoryConversationStore::class, function (Application $app): InMemoryConversationStore {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new InMemoryConversationStore(
                retentionDays: $this->nullableIntConfig($config, 'ai-agent-kit.memory.in_memory.retention_days'),
            );
        });

        $this->app->singleton(ConversationStore::class, function (Application $app): ConversationStore {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return match ($this->memoryDriver($config)) {
                'database' => $app->make(DatabaseConversationStore::class),
                'in_memory' => $app->make(InMemoryConversationStore::class),
                default => throw new RuntimeException('Unsupported memory driver.'),
            };
        });

        $this->app->singleton(DatabaseConversationRetentionPurger::class, function (Application $app): DatabaseConversationRetentionPurger {
            /** @var DatabaseManager $database */
            $database = $app->make(DatabaseManager::class);
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new DatabaseConversationRetentionPurger(
                database: $database,
                connectionName: $this->nullableStringConfig($config, 'ai-agent-kit.memory.database.connection'),
                conversationsTable: $this->stringConfig($config, 'ai-agent-kit.memory.database.conversations_table'),
            );
        });

        $this->app->singleton(ConversationRetentionPurger::class, function (Application $app): ConversationRetentionPurger {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return match ($this->memoryDriver($config)) {
                'database' => $app->make(DatabaseConversationRetentionPurger::class),
                'in_memory' => $app->make(InMemoryConversationStore::class),
                default => throw new RuntimeException('Unsupported memory driver.'),
            };
        });

        $this->app->singleton(StoreBackedConversationContextManager::class, function (Application $app): StoreBackedConversationContextManager {
            return new StoreBackedConversationContextManager(
                conversationStore: $app->make(ConversationStore::class),
            );
        });

        $this->app->singleton(ConversationContextManager::class, function (Application $app): ConversationContextManager {
            return $app->make(StoreBackedConversationContextManager::class);
        });

        $this->app->singleton(SynchronousPipelineRunner::class, function (Application $app): SynchronousPipelineRunner {
            return new SynchronousPipelineRunner(
                conversationContextManager: $app->make(ConversationContextManager::class),
            );
        });

        $this->app->singleton(PipelineRunner::class, function (Application $app): PipelineRunner {
            return $app->make(SynchronousPipelineRunner::class);
        });

        $this->app->singleton(LaravelQueuedPipelineDispatcher::class, function (): LaravelQueuedPipelineDispatcher {
            return new LaravelQueuedPipelineDispatcher();
        });

        $this->app->singleton(QueuedPipelineDispatcher::class, function (Application $app): QueuedPipelineDispatcher {
            return $app->make(LaravelQueuedPipelineDispatcher::class);
        });

        $this->app->singleton(NullConversationSummarizer::class, function (Application $app): NullConversationSummarizer {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var int $triggerMessageCount */
            $triggerMessageCount = $config->get('ai-agent-kit.summarization.trigger_message_count', 20);

            return new NullConversationSummarizer(
                enabled: (bool)$config->get('ai-agent-kit.summarization.enabled', false),
                triggerMessageCount: $triggerMessageCount,
            );
        });

        $this->app->singleton(ConversationSummarizer::class, function (Application $app): ConversationSummarizer {
            return $app->make(NullConversationSummarizer::class);
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

        /** @var array{enabled?: bool}|null $validation */
        $validation = $config->get('ai-agent-kit.validation');

        $enabled = !is_array($validation) || (bool)($validation['enabled'] ?? true);

        if ($enabled) {
            $app->make(ConfigValidator::class)->validateCurrentConfig();
        }
    }

    private function nullableStringConfig(ConfigRepository $config, string $key): ?string
    {
        $value = $config->get($key);

        if ($value === null) {
            return null;
        }

        return is_string($value) && $value !== ''
          ? $value
          : throw new RuntimeException("Configuration key [{$key}] must be null or a non-empty string.");
    }

    private function stringConfig(ConfigRepository $config, string $key): string
    {
        $value = $config->get($key);

        return is_string($value) && $value !== ''
          ? $value
          : throw new RuntimeException("Configuration key [{$key}] must be a non-empty string.");
    }

    private function nullableIntConfig(ConfigRepository $config, string $key): ?int
    {
        $value = $config->get($key);

        if ($value === null) {
            return null;
        }

        return is_int($value)
          ? $value
          : throw new RuntimeException("Configuration key [{$key}] must be null or an integer.");
    }

    private function memoryDriver(ConfigRepository $config): string
    {
        return $this->stringConfig($config, 'ai-agent-kit.memory.default_driver');
    }
}
