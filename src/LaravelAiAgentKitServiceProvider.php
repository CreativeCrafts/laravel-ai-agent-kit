<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Commands\MakePromptCommand;
use CreativeCrafts\LaravelAiAgentKit\Commands\MakeToolCommand;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\RetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\LaravelQueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\CompiledBlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeConversationMemoryBridge;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\NullConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Memory\RedisConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\StoreBackedConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Resilience\ConfigRetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Security\LaravelEncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
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
          ->hasMigration('create_ai_agent_conversation_messages_table')
          ->hasCommands([MakePromptCommand::class, MakeToolCommand::class]);
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
                events: $app->make(Dispatcher::class),
            );
        });

        $this->app->singleton(FailoverProviderSelector::class, function (Application $app): FailoverProviderSelector {
            return $app->make(ConfiguredFailoverProviderSelector::class);
        });

        $this->app->singleton(ConfigRetryPolicyResolver::class, function (Application $app): ConfigRetryPolicyResolver {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfigRetryPolicyResolver($config);
        });

        $this->app->singleton(RetryPolicyResolver::class, function (Application $app): RetryPolicyResolver {
            return $app->make(ConfigRetryPolicyResolver::class);
        });

        $this->app->singleton(InMemoryCircuitBreakerManager::class, function (Application $app): InMemoryCircuitBreakerManager {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new InMemoryCircuitBreakerManager($config);
        });

        $this->app->singleton(CircuitBreakerManager::class, function (Application $app): CircuitBreakerManager {
            return $app->make(InMemoryCircuitBreakerManager::class);
        });

        $this->app->singleton(LaravelEncryptionService::class, function (Application $app): LaravelEncryptionService {
            /** @var Encrypter $encrypter */
            $encrypter = $app->make(Encrypter::class);

            return new LaravelEncryptionService($encrypter);
        });

        $this->app->singleton(EncryptionService::class, function (Application $app): EncryptionService {
            return $app->make(LaravelEncryptionService::class);
        });

        $this->app->singleton(DatabaseConversationStore::class, function (Application $app): DatabaseConversationStore {
            /** @var DatabaseManager $database */
            $database = $app->make(DatabaseManager::class);
            /** @var EncryptionService $encryptionService */
            $encryptionService = $app->make(EncryptionService::class);
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new DatabaseConversationStore(
                database: $database,
                encryptionService: $encryptionService,
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

        $this->app->singleton(RedisConversationStore::class, function (Application $app): RedisConversationStore {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new RedisConversationStore(
                app: $app,
                connectionName: $this->nullableStringConfig($config, 'ai-agent-kit.memory.redis.connection'),
                keyPrefix: $this->stringConfig($config, 'ai-agent-kit.memory.redis.prefix'),
                driverName: $this->stringConfig($config, 'ai-agent-kit.memory.redis.driver_name'),
                retentionDays: $this->nullableIntConfig($config, 'ai-agent-kit.memory.redis.retention_days'),
            );
        });

        $this->app->singleton(ConversationStore::class, function (Application $app): ConversationStore {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return match ($this->memoryDriver($config)) {
                'database' => $app->make(DatabaseConversationStore::class),
                'in_memory' => $app->make(InMemoryConversationStore::class),
                'redis' => $app->make(RedisConversationStore::class),
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
                'redis' => $app->make(RedisConversationStore::class),
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

        $this->app->singleton(InMemoryPromptRepository::class, function (): InMemoryPromptRepository {
            return new InMemoryPromptRepository();
        });

        $this->app->singleton(PromptRepository::class, function (Application $app): PromptRepository {
            return $app->make(InMemoryPromptRepository::class);
        });

        $this->app->singleton(DenyAllToolAuthorizer::class, function (): DenyAllToolAuthorizer {
            return new DenyAllToolAuthorizer();
        });

        $this->app->singleton(ToolAuthorizer::class, function (Application $app): ToolAuthorizer {
            return $app->make(DenyAllToolAuthorizer::class);
        });

        $this->app->singleton(InMemoryToolRegistry::class, function (Application $app): InMemoryToolRegistry {
            return new InMemoryToolRegistry(
                authorizer: $app->make(ToolAuthorizer::class),
            );
        });

        $this->app->singleton(ToolRegistry::class, function (Application $app): ToolRegistry {
            return $app->make(InMemoryToolRegistry::class);
        });

        $this->app->singleton(InMemoryVectorStore::class, function (): InMemoryVectorStore {
            return new InMemoryVectorStore();
        });

        $this->app->singleton(VectorStoreInterface::class, function (Application $app): VectorStoreInterface {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return match ($this->stringConfig($config, 'ai-agent-kit.vector.default_driver')) {
                'in_memory' => $app->make(InMemoryVectorStore::class),
                default => throw new RuntimeException('Unsupported vector driver.'),
            };
        });

        $this->app->singleton(SdkToolMaterializer::class, function (Application $app): SdkToolMaterializer {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new SdkToolMaterializer(
                toolRegistry: $app->make(ToolRegistry::class),
                config: $config,
            );
        });

        $this->app->singleton(PromptBlueprintCompiler::class, function (Application $app): PromptBlueprintCompiler {
            return new PromptBlueprintCompiler(
                promptExecutionMapper: $app->make(PromptExecutionMapper::class),
            );
        });

        $this->app->singleton(BlueprintCompiler::class, function (Application $app): BlueprintCompiler {
            return $app->make(PromptBlueprintCompiler::class);
        });

        $this->app->singleton(RuntimeConversationMemoryBridge::class, function (Application $app): RuntimeConversationMemoryBridge {
            return new RuntimeConversationMemoryBridge(
                conversationContextManager: $app->make(ConversationContextManager::class),
            );
        });

        $this->app->singleton(SdkAiRuntime::class, function (Application $app): SdkAiRuntime {
            return new SdkAiRuntime(
                toolMaterializer: $app->make(SdkToolMaterializer::class),
                runtimeConversationMemoryBridge: $app->make(RuntimeConversationMemoryBridge::class),
            );
        });

        $this->app->singleton(AiRuntime::class, function (Application $app): AiRuntime {
            return $app->make(SdkAiRuntime::class);
        });

        $this->app->singleton(CompiledBlueprintRunner::class, function (Application $app): CompiledBlueprintRunner {
            return new CompiledBlueprintRunner(
                blueprintCompiler: $app->make(BlueprintCompiler::class),
                aiRuntime: $app->make(AiRuntime::class),
            );
        });

        $this->app->singleton(BlueprintRunner::class, function (Application $app): BlueprintRunner {
            return $app->make(CompiledBlueprintRunner::class);
        });

        $this->app->singleton(SynchronousPipelineRunner::class, function (Application $app): SynchronousPipelineRunner {
            return new SynchronousPipelineRunner(
                conversationContextManager: $app->make(ConversationContextManager::class),
                events: $app->make(Dispatcher::class),
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
