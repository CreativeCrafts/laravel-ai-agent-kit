<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Commands\MakeAgentCommand;
use CreativeCrafts\LaravelAiAgentKit\Commands\MakePipelineCommand;
use CreativeCrafts\LaravelAiAgentKit\Commands\MakePromptCommand;
use CreativeCrafts\LaravelAiAgentKit\Commands\MakeToolCommand;
use CreativeCrafts\LaravelAiAgentKit\Commands\PurgeConversationsCommand;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\DelegationPolicyEngine;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\RetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\ContainerAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ConfigurableDelegationPolicyEngine;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationPolicyMode;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\LaravelQueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\CompiledBlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\MiddlewareExecutingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeConversationMemoryBridge;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkEmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkRerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkTranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\FallingBackToLegacyLaravelAiConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\LegacyLaravelAiDatabaseConversationReader;
use CreativeCrafts\LaravelAiAgentKit\Memory\NullConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Memory\RedisConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\RetentionPurgeService;
use CreativeCrafts\LaravelAiAgentKit\Memory\StoreBackedConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Observability\SdkTelemetryNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Prompts\FilePromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Resilience\ConfigRetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\PipelineBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Security\DefaultRedactor;
use CreativeCrafts\LaravelAiAgentKit\Security\LaravelEncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Providers\Tools\WebFetch;
use Laravel\Ai\Providers\Tools\WebSearch;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Closure;

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
          ->hasCommands([
            MakeAgentCommand::class,
            MakePipelineCommand::class,
            MakePromptCommand::class,
            MakeToolCommand::class,
            PurgeConversationsCommand::class,
          ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ContainerAgentRegistry::class, function (Application $app): ContainerAgentRegistry {
            return new ContainerAgentRegistry($app);
        });

        $this->app->singleton(AgentRegistry::class, function (Application $app): AgentRegistry {
            return $app->make(ContainerAgentRegistry::class);
        });

        $this->app->singleton(ConfigurableDelegationPolicyEngine::class, function (Application $app): ConfigurableDelegationPolicyEngine {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfigurableDelegationPolicyEngine(
                agentRegistry: $app->make(AgentRegistry::class),
                mode: $this->delegationPolicyModeConfig($config, 'ai-agent-kit.orchestration.delegation_policy.mode'),
                allowlist: $this->delegationPolicyAllowlistConfig($config, 'ai-agent-kit.orchestration.delegation_policy.allowlist'),
                rewrites: $this->delegationPolicyRewritesConfig($config, 'ai-agent-kit.orchestration.delegation_policy.rewrites'),
            );
        });

        $this->app->singleton(DelegationPolicyEngine::class, function (Application $app): DelegationPolicyEngine {
            return $app->make(ConfigurableDelegationPolicyEngine::class);
        });

        $this->app->singleton(SynchronousAgentOrchestrator::class, function (Application $app): SynchronousAgentOrchestrator {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new SynchronousAgentOrchestrator(
                agentRegistry: $app->make(AgentRegistry::class),
                delegationPolicyEngine: $app->make(DelegationPolicyEngine::class),
                maxExecutionDepth: $this->positiveIntConfig($config, 'ai-agent-kit.budgets.max_orchestration_depth', 25),
                maxExecutionSteps: $this->positiveIntConfig($config, 'ai-agent-kit.budgets.max_steps', 50),
                agentProviderProfileSelector: $app->make(AgentProviderProfileSelector::class),
                events: $app->make(Dispatcher::class),
                redactor: $app->make(Redactor::class),
            );
        });

        $this->app->singleton(AgentOrchestrator::class, function (Application $app): AgentOrchestrator {
            return $app->make(SynchronousAgentOrchestrator::class);
        });

        $this->app->singleton(AgentKitManager::class, function (Application $app): AgentKitManager {
            return new AgentKitManager(
                textEvaluation: $app->make(TextToStructuredEvaluation::class),
                audioEvaluation: $app->make(AudioToTextToEvaluation::class),
                orchestrator: $app->make(AgentOrchestrator::class),
                blueprintRunner: $app->make(BlueprintRunner::class),
            );
        });

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

        $this->app->singleton(ConfiguredAgentProviderProfileSelector::class, function (Application $app): ConfiguredAgentProviderProfileSelector {
            return new ConfiguredAgentProviderProfileSelector(
                providerRegistry: $app->make(ProviderRegistry::class),
            );
        });

        $this->app->singleton(AgentProviderProfileSelector::class, function (Application $app): AgentProviderProfileSelector {
            return $app->make(ConfiguredAgentProviderProfileSelector::class);
        });

        $this->app->singleton(ConfiguredFailoverProviderSelector::class, function (Application $app): ConfiguredFailoverProviderSelector {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfiguredFailoverProviderSelector(
                config: $config,
                providerRegistry: $app->make(ProviderRegistry::class),
                events: $app->make(Dispatcher::class),
                circuitBreakerManager: $app->make(CircuitBreakerManager::class),
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

        $this->app->singleton(PipelineBudgetEnforcer::class, function (Application $app): PipelineBudgetEnforcer {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new PipelineBudgetEnforcer($config);
        });

        $this->app->singleton(LaravelEncryptionService::class, function (Application $app): LaravelEncryptionService {
            /** @var Encrypter $encrypter */
            $encrypter = $app->make(Encrypter::class);

            return new LaravelEncryptionService($encrypter);
        });

        $this->app->singleton(EncryptionService::class, function (Application $app): EncryptionService {
            return $app->make(LaravelEncryptionService::class);
        });

        $this->app->singleton(DefaultRedactor::class, function (): DefaultRedactor {
            return new DefaultRedactor();
        });

        $this->app->singleton(Redactor::class, function (Application $app): Redactor {
            return $app->make(DefaultRedactor::class);
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

            $inner = match ($this->memoryDriver($config)) {
                'database' => $app->make(DatabaseConversationStore::class),
                'in_memory' => $app->make(InMemoryConversationStore::class),
                'redis' => $app->make(RedisConversationStore::class),
                default => throw new RuntimeException('Unsupported memory driver.'),
            };

            if ($this->memoryDriver($config) === 'database'
                && (bool)$config->get('ai-agent-kit.memory.laravel_ai_legacy.enabled', false)) {
                /** @var DatabaseManager $database */
                $database = $app->make(DatabaseManager::class);

                $legacyReader = new LegacyLaravelAiDatabaseConversationReader(
                    database: $database,
                    connectionName: $this->nullableStringConfig($config, 'ai-agent-kit.memory.laravel_ai_legacy.connection')
                        ?? $this->nullableStringConfig($config, 'ai-agent-kit.memory.database.connection'),
                    conversationsTable: $this->stringConfig($config, 'ai-agent-kit.memory.laravel_ai_legacy.conversations_table'),
                    messagesTable: $this->stringConfig($config, 'ai-agent-kit.memory.laravel_ai_legacy.messages_table'),
                );

                return new FallingBackToLegacyLaravelAiConversationStore(
                    inner: $inner,
                    legacyReader: $legacyReader,
                );
            }

            return $inner;
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

        $this->app->singleton(RetentionPurgeService::class, function (Application $app): RetentionPurgeService {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new RetentionPurgeService(
                purger: $app->make(ConversationRetentionPurger::class),
                memoryDriver: $this->memoryDriver($config),
            );
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

        $this->app->singleton(FilePromptRepository::class, function (Application $app): FilePromptRepository {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            $configuredRootPath = $this->nullableStringConfig($config, 'ai-agent-kit.prompts.file.root_path');

            return new FilePromptRepository($configuredRootPath ?? $app->basePath('resources/prompts'));
        });

        $this->app->singleton(PromptRepository::class, function (Application $app): PromptRepository {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return match ($this->promptDriver($config)) {
                'in_memory' => $app->make(InMemoryPromptRepository::class),
                'file' => $app->make(FilePromptRepository::class),
                default => throw new RuntimeException('Unsupported prompt repository driver.'),
            };
        });

        $this->app->singleton(DenyAllToolAuthorizer::class, function (): DenyAllToolAuthorizer {
            return new DenyAllToolAuthorizer();
        });

        $this->app->singleton(ToolAuthorizer::class, function (Application $app): ToolAuthorizer {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            $authorizerClass = $config->get('ai-agent-kit.tools.authorizer', DenyAllToolAuthorizer::class);

            if (!is_string($authorizerClass) || $authorizerClass === '') {
                throw new RuntimeException('Configuration key [ai-agent-kit.tools.authorizer] must be a non-empty class-string implementing the ToolAuthorizer contract.');
            }

            if (!class_exists($authorizerClass) || !is_a($authorizerClass, ToolAuthorizer::class, true)) {
                throw new RuntimeException("Configured tool authorizer [{$authorizerClass}] must implement the ToolAuthorizer contract.");
            }

            $authorizer = $app->make($authorizerClass);

            if (!$authorizer instanceof ToolAuthorizer) {
                throw new RuntimeException("Resolved tool authorizer [{$authorizerClass}] must implement the ToolAuthorizer contract.");
            }

            return $authorizer;
        });

        $this->app->singleton(InMemoryToolRegistry::class, function (Application $app): InMemoryToolRegistry {
            return new InMemoryToolRegistry(
                authorizer: $app->make(ToolAuthorizer::class),
            );
        });

        $this->app->singleton(ToolRegistry::class, function (Application $app): ToolRegistry {
            return $app->make(InMemoryToolRegistry::class);
        });

        $this->app->singleton(InMemoryProviderToolRegistry::class, function (Application $app): InMemoryProviderToolRegistry {
            $registry = new InMemoryProviderToolRegistry();

            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            $this->seedProviderToolRegistryFromConfig($registry, $config);

            return $registry;
        });

        $this->app->singleton(ProviderToolRegistry::class, function (Application $app): ProviderToolRegistry {
            return $app->make(InMemoryProviderToolRegistry::class);
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
            return new SdkToolMaterializer(
                toolRegistry: $app->make(ToolRegistry::class),
            );
        });

        $this->app->singleton(ProviderToolMaterializer::class, function (Application $app): ProviderToolMaterializer {
            return new ProviderToolMaterializer(
                providerToolRegistry: $app->make(ProviderToolRegistry::class),
                authorizer: $app->make(ToolAuthorizer::class),
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
                config: $app->make(ConfigRepository::class),
                events: $app->make(Dispatcher::class),
            );
        });

        $this->app->singleton(SdkTelemetryNormalizer::class, function (Application $app): SdkTelemetryNormalizer {
            return new SdkTelemetryNormalizer(
                events: $app->make(Dispatcher::class),
                redactor: $app->make(Redactor::class),
            );
        });

        $this->app->singleton(SdkEmbeddingsRuntime::class, static fn (): SdkEmbeddingsRuntime => new SdkEmbeddingsRuntime());
        $this->app->singleton(SdkImageGenerationRuntime::class, static fn (): SdkImageGenerationRuntime => new SdkImageGenerationRuntime());
        $this->app->singleton(SdkRerankingRuntime::class, static fn (): SdkRerankingRuntime => new SdkRerankingRuntime());
        $this->app->singleton(SdkTranscriptionRuntime::class, static fn (): SdkTranscriptionRuntime => new SdkTranscriptionRuntime());

        $this->app->singleton(TranscriptionRuntime::class, function (Application $app): TranscriptionRuntime {
            return $this->resolveModalityRuntime(
                $app,
                configKey: 'ai-agent-kit.modalities.transcription',
                sdk: $app->make(SdkTranscriptionRuntime::class),
                contract: TranscriptionRuntime::class,
            );
        });

        $this->app->singleton(EmbeddingsRuntime::class, function (Application $app): EmbeddingsRuntime {
            return $this->resolveModalityRuntime(
                $app,
                configKey: 'ai-agent-kit.modalities.embeddings',
                sdk: $app->make(SdkEmbeddingsRuntime::class),
                contract: EmbeddingsRuntime::class,
            );
        });

        $this->app->singleton(ImageGenerationRuntime::class, function (Application $app): ImageGenerationRuntime {
            return $this->resolveModalityRuntime(
                $app,
                configKey: 'ai-agent-kit.modalities.image_generation',
                sdk: $app->make(SdkImageGenerationRuntime::class),
                contract: ImageGenerationRuntime::class,
            );
        });

        $this->app->singleton(RerankingRuntime::class, function (Application $app): RerankingRuntime {
            return $this->resolveModalityRuntime(
                $app,
                configKey: 'ai-agent-kit.modalities.reranking',
                sdk: $app->make(SdkRerankingRuntime::class),
                contract: RerankingRuntime::class,
            );
        });

        $this->app->singleton(SdkAiRuntime::class, function (Application $app): SdkAiRuntime {
            return new SdkAiRuntime(
                toolMaterializer: $app->make(SdkToolMaterializer::class),
                providerToolMaterializer: $app->make(ProviderToolMaterializer::class),
                runtimeConversationMemoryBridge: $app->make(RuntimeConversationMemoryBridge::class),
                runtimeBudgetEnforcer: $app->make(RuntimeBudgetEnforcer::class),
                container: $app,
                events: $app->make(Dispatcher::class),
                redactor: $app->make(Redactor::class),
            );
        });

        $this->app->singleton(AiRuntime::class, function (Application $app): AiRuntime {
            $inner = $app->make(SdkAiRuntime::class);
            $middleware = $this->resolveRuntimeMiddlewareStack($app);

            if ($middleware === []) {
                return $inner;
            }

            return new MiddlewareExecutingAiRuntime($inner, $middleware);
        });

        $this->app->singleton(StreamingAiRuntime::class, function (Application $app): StreamingAiRuntime {
            $runtime = $app->make(AiRuntime::class);

            if (!$runtime instanceof StreamingAiRuntime) {
                throw new RuntimeException(
                    sprintf('Resolved %s must implement %s for streaming.', AiRuntime::class, StreamingAiRuntime::class),
                );
            }

            return $runtime;
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
                redactor: $app->make(Redactor::class),
                budgetEnforcer: $app->make(PipelineBudgetEnforcer::class),
                retryPolicyResolver: $app->make(RetryPolicyResolver::class),
            );
        });

        $this->app->singleton(PipelineRunner::class, function (Application $app): PipelineRunner {
            return $app->make(SynchronousPipelineRunner::class);
        });

        $this->app->singleton(LaravelQueuedPipelineDispatcher::class, function (Application $app): LaravelQueuedPipelineDispatcher {
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

        if ($this->memoryDriver($config) === 'redis') {
            $app->make(RedisConversationStore::class);
        }

        $normalizer = $app->make(SdkTelemetryNormalizer::class);
        $events = $app->make(Dispatcher::class);

        $events->listen(PromptingAgent::class, [$normalizer, 'handlePromptingAgent']);
        $events->listen(AgentPrompted::class, [$normalizer, 'handleAgentPrompted']);
        $events->listen(InvokingTool::class, [$normalizer, 'handleInvokingTool']);
        $events->listen(ToolInvoked::class, [$normalizer, 'handleToolInvoked']);
    }

    private function delegationPolicyModeConfig(ConfigRepository $config, string $key): DelegationPolicyMode
    {
        $value = $config->get($key, DelegationPolicyMode::STATIC_ONLY->value);

        if ($value instanceof DelegationPolicyMode) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Configuration key [{$key}] must be a non-empty string or a delegation policy mode enum.");
        }

        $mode = DelegationPolicyMode::tryFrom($value);

        if ($mode === null) {
            throw new RuntimeException(
                sprintf(
                    'Configuration key [%s] must be one of [%s].',
                    $key,
                    implode(', ', array_map(static fn (DelegationPolicyMode $candidate): string => $candidate->value, DelegationPolicyMode::cases())),
                ),
            );
        }

        return $mode;
    }

    /**
     * @return array<string, list<string>>
     */
    private function delegationPolicyAllowlistConfig(ConfigRepository $config, string $key): array
    {
        $allowlist = $this->arrayConfig($config, $key);
        $normalized = [];

        foreach ($allowlist as $sourceAgentKey => $targets) {
            if (!is_string($sourceAgentKey) || $sourceAgentKey === '') {
                throw new RuntimeException("Configuration key [{$key}] must contain non-empty string keys.");
            }

            if (!is_array($targets)) {
                throw new RuntimeException(
                    "Configuration key [{$key}] must contain arrays of non-empty string target agent keys.",
                );
            }

            $normalizedTargets = [];

            foreach ($targets as $target) {
                if (!is_string($target) || $target === '') {
                    throw new RuntimeException(
                        "Configuration key [{$key}] must contain arrays of non-empty string target agent keys.",
                    );
                }

                $normalizedTargets[] = $target;
            }

            $normalized[$sourceAgentKey] = $normalizedTargets;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    private function arrayConfig(ConfigRepository $config, string $key, array $default = []): array
    {
        $value = $config->get($key, $default);

        return is_array($value)
          ? $value
          : throw new RuntimeException("Configuration key [{$key}] must be an array.");
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function delegationPolicyRewritesConfig(ConfigRepository $config, string $key): array
    {
        $rewrites = $this->arrayConfig($config, $key);
        $normalized = [];

        foreach ($rewrites as $sourceAgentKey => $mapping) {
            if (!is_string($sourceAgentKey) || $sourceAgentKey === '') {
                throw new RuntimeException("Configuration key [{$key}] must contain non-empty string keys.");
            }

            if (!is_array($mapping)) {
                throw new RuntimeException(
                    "Configuration key [{$key}] must contain rewrite maps with non-empty string source and target agent keys.",
                );
            }

            $normalizedMapping = [];

            foreach ($mapping as $fromTarget => $toTarget) {
                if (!is_string($fromTarget) || $fromTarget === '' || !is_string($toTarget) || $toTarget === '') {
                    throw new RuntimeException(
                        "Configuration key [{$key}] must contain rewrite maps with non-empty string source and target agent keys.",
                    );
                }

                $normalizedMapping[$fromTarget] = $toTarget;
            }

            $normalized[$sourceAgentKey] = $normalizedMapping;
        }

        return $normalized;
    }

    private function positiveIntConfig(ConfigRepository $config, string $key, int $default): int
    {
        $value = $config->get($key, $default);

        return is_int($value) && $value >= 1
          ? $value
          : throw new RuntimeException("Configuration key [{$key}] must be an integer >= 1.");
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

    private function promptDriver(ConfigRepository $config): string
    {
        return $this->stringConfig($config, 'ai-agent-kit.prompts.default_driver');
    }

    /**
     * @return list<RuntimeMiddleware>
     */
    private function resolveRuntimeMiddlewareStack(Application $app): array
    {
        /** @var ConfigRepository $config */
        $config = $app->make(ConfigRepository::class);
        $entries = $config->get('ai-agent-kit.runtime.middleware', []);

        if (!is_array($entries)) {
            return [];
        }

        $middleware = [];

        foreach ($entries as $index => $class) {
            if (!is_string($class) || $class === '') {
                throw new RuntimeException(
                    sprintf('Configuration key [ai-agent-kit.runtime.middleware.%s] must be a non-empty class-string.', $index),
                );
            }

            $instance = $app->make($class);

            if (!$instance instanceof RuntimeMiddleware) {
                throw new RuntimeException(
                    sprintf('Runtime middleware [%s] must implement %s.', $class, RuntimeMiddleware::class),
                );
            }

            $middleware[] = $instance;
        }

        return $middleware;
    }

    private function seedProviderToolRegistryFromConfig(
        InMemoryProviderToolRegistry $registry,
        ConfigRepository $config,
    ): void {
        $providerTools = $config->get('ai-agent-kit.tools.provider_tools', []);

        if (!is_array($providerTools)) {
            return;
        }

        foreach ($providerTools as $name => $definition) {
            if (!is_string($name)) {
                continue;
            }
            if ($name === '') {
                continue;
            }
            if (!is_array($definition)) {
                continue;
            }
            $stringKeyedDefinition = [];

            foreach ($definition as $key => $value) {
                if (is_string($key)) {
                    $stringKeyedDefinition[$key] = $value;
                }
            }

            if (($stringKeyedDefinition['enabled'] ?? true) !== true) {
                continue;
            }

            $factory = $this->providerToolFactoryFor($stringKeyedDefinition);

            if (!$factory instanceof Closure) {
                continue;
            }

            $registry->register($name, $factory);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function providerToolFactoryFor(array $definition): ?Closure
    {
        $type = $definition['type'] ?? null;

        if (!is_string($type) || $type === '') {
            return null;
        }

        return match ($type) {
            'web_search' => $this->webSearchFactory($definition),
            'web_fetch' => $this->webFetchFactory($definition),
            'file_search' => $this->fileSearchFactory($definition),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function webSearchFactory(array $definition): Closure
    {
        $maxSearches = is_int($definition['max_searches'] ?? null) ? $definition['max_searches'] : null;
        /** @var list<string> $allowedDomains */
        $allowedDomains = is_array($definition['allowed_domains'] ?? null)
          ? array_values(array_filter($definition['allowed_domains'], static fn ($value): bool => is_string($value) && $value !== ''))
          : [];

        $location = is_array($definition['location'] ?? null) ? $definition['location'] : null;

        return function () use ($maxSearches, $allowedDomains, $location): WebSearch {
            $tool = new WebSearch(maxSearches: $maxSearches, allowedDomains: $allowedDomains);

            if ($location !== null) {
                $tool->location(
                    city: is_string($location['city'] ?? null) ? $location['city'] : null,
                    region: is_string($location['region'] ?? null) ? $location['region'] : null,
                    country: is_string($location['country'] ?? null) ? $location['country'] : null,
                );
            }

            return $tool;
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function webFetchFactory(array $definition): Closure
    {
        /** @var list<string> $allowedDomains */
        $allowedDomains = is_array($definition['allowed_domains'] ?? null)
          ? array_values(array_filter($definition['allowed_domains'], static fn ($value): bool => is_string($value) && $value !== ''))
          : [];

        return static fn (): WebFetch => new WebFetch(allowedDomains: $allowedDomains);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function fileSearchFactory(array $definition): Closure
    {
        /** @var list<string> $stores */
        $stores = is_array($definition['stores'] ?? null)
          ? array_values(array_filter($definition['stores'], static fn ($value): bool => is_string($value) && $value !== ''))
          : [];

        $filters = is_array($definition['filters'] ?? null) ? $definition['filters'] : null;

        return static fn (): FileSearch => new FileSearch(stores: $stores, where: $filters);
    }

    /**
     * @template T of object
     *
     * @param  T  $sdk
     * @param  class-string<T>  $contract
     * @return T
     */
    private function resolveModalityRuntime(Application $app, string $configKey, object $sdk, string $contract)
    {
        /** @var ConfigRepository $config */
        $config = $app->make(ConfigRepository::class);
        $block = $config->get($configKey, []);

        if (!is_array($block)) {
            return $sdk;
        }

        $driver = $block['default_driver'] ?? 'sdk';

        if (!is_string($driver) || $driver === '' || $driver === 'sdk') {
            return $sdk;
        }

        $resolved = $app->make($driver);

        if (!$resolved instanceof $contract) {
            throw new RuntimeException(
                sprintf('Modality driver [%s] must resolve to an object implementing %s.', $driver, $contract),
            );
        }

        return $resolved;
    }
}
