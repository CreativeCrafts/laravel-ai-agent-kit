# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package that delivers a structured agent-workflow toolkit built on top of the official Laravel AI SDK. It provides provider abstraction, pipeline orchestration,
queued execution, and package foundations for building AI-powered application flows safely and predictably.

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

Laravel AI Agent Kit requires the official Laravel AI SDK at runtime. The package now declares `laravel/ai` as a Composer dependency, so Composer will install the SDK automatically when you require
this package.

Publish the Laravel AI SDK configuration and migrations first:

~~~bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
~~~

Then publish and run this package's migrations:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
~~~

Publish this package's configuration file:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

Optionally, publish the views:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-views"
~~~

## Configuration

The package validates its configuration during boot by default.

At least one enabled provider must exist, `default_provider` must reference an enabled configured provider, and `failover_order` must include the default provider.

The default memory driver is `in_memory`. That default is explicit, non-persistent, and safe for tests, local development, and ephemeral runs. Switch `memory.default_driver` to `database` when you
want encrypted persistent storage and retention-based purging, or to `redis` when you need shared ephemeral memory across workers.

Retry and circuit breaker resilience settings are configured explicitly under `resilience`. Retry policy evaluation remains bounded by `budgets.max_retries_per_step`, and the circuit breaker exposes
clear `closed`, `open`, and `half_open` semantics with configurable thresholds and reset timing.

Pipeline lifecycle and failover telemetry are emitted through Laravel events with redacted defaults. Event payloads expose safe metadata such as run identifiers, provider names, step classes, counts,
and key lists rather than raw prompt, input, metadata, or provider option values by default.

Example configuration:

~~~php
return [
    'validation' => [
        'enabled' => true,
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],
    ],

    'default_provider' => 'null',

    'failover_order' => ['null'],

    'budgets' => [
        'max_steps' => 20,
        'max_tool_calls' => 50,
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 120,
        'max_tokens' => null,
        'max_cost_usd' => null,
    ],

    'resilience' => [
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'backoff' => [
                'strategy' => 'exponential',
                'base_delay_ms' => 250,
                'max_delay_ms' => 2000,
                'multiplier' => 2.0,
            ],
        ],
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 3,
            'reset_timeout_seconds' => 60,
            'half_open_success_threshold' => 1,
        ],
    ],

    'memory' => [
        'default_driver' => 'in_memory',

        'in_memory' => [
            'retention_days' => null,
        ],

        'database' => [
            'connection' => null,
            'conversations_table' => 'ai_agent_conversations',
            'messages_table' => 'ai_agent_conversation_messages',
            'driver_name' => 'database',
            'retention_days' => 30,
            'encrypt_payloads' => true,
        ],

        'redis' => [
            'connection' => null,
            'prefix' => 'ai_agent_memory:',
            'driver_name' => 'redis',
            'retention_days' => 7,
        ],
    ],
];
~~~

## Usage

Resolve the configured provider registry or default provider selector through the container:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;

$registry = app(ProviderRegistry::class);
$selector = app(ProviderSelector::class);

$defaultProvider = $selector->selectDefault();
$provider = $registry->get('null');
~~~

Register first-class agents explicitly through the package agent registry in your application service provider:

~~~php
use App\Agents\CancellationAgent;
use App\Agents\CustomerSupportAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(AgentRegistry $agents): void
    {
        $agents->registerMany([
            CustomerSupportAgent::class,
            CancellationAgent::class,
        ]);
    }
}
~~~

Registered agents are resolved through the Laravel container and looked up by the stable agent key returned from their package-owned `AgentDefinition`.

Run the package-owned `TextToStructuredEvaluation` blueprint when you want one structured evaluation result from one orchestration call while keeping the internal coordinator-to-specialist flow hidden
behind the public API:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;

$result = app(TextToStructuredEvaluation::class)->evaluate(
    new TextToStructuredEvaluationRequest(
        subject: 'support reply',
        text: 'We can refund the unused portion of your subscription within five business days.',
        enabledDimensions: ['clarity', 'accuracy', 'completeness'],
        promptVersion: '1.0.0',
    ),
);

$summary = $result->summary;
$recommendedAction = $result->recommendedAction;
$clarityScore = $result->dimension('clarity')?->score;
~~~

The blueprint returns a fixed package-owned result schema with:

- `summary`
- `recommendedAction`
- `confidence`
- `enabledDimensions`
- `dimensions` keyed by dimension name, each with `score`, `summary`, and `evidence`
- `orchestrationSummary`, `finalAgent`, `promptName`, and `promptVersion`

The enabled dimensions are caller-configurable, but the top-level result contract remains package-owned and stable.

Before running the blueprint, register the prompt template referenced by `promptName` and `promptVersion`. The specialist stage expects the model output to be valid JSON matching the fixed package
schema.

Build and run a synchronous pipeline with typed steps:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

$pipeline = PipelineBuilder::make()
    ->addStep(new class implements PipelineStep
    {
        public function handle(RunContext $context): RunContext
        {
            return $context
                ->withStateValue('normalized', true)
                ->incrementStepCount();
        }
    })
    ->build();

$runner = app(PipelineRunner::class);

$result = $runner->run(
    $pipeline,
    new RunContext(
        runId: 'run-001',
        input: ['text' => 'Hello world'],
    ),
);
~~~

Dispatch a queued pipeline using a typed pipeline definition and explicit result handler:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class NormalizeTranscriptPipeline implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();
    }
}

final class PersistPipelineResult implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
        // Persist or publish the final pipeline state.
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        report($throwable);
    }
}

$dispatcher = app(QueuedPipelineDispatcher::class);

$dispatcher->dispatch(
    definition: new NormalizeTranscriptPipeline(),
    context: new RunContext(
        runId: 'queued-run-001',
        input: ['text' => 'Queued pipeline input'],
    ),
    handler: new PersistPipelineResult(),
    options: new QueueDispatchOptions(
        queue: 'ai-pipelines',
        connection: 'sync',
    ),
);
~~~

Use conversation memory through the package-owned context manager and store contracts:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

$store = app(ConversationStore::class);

$conversation = $store->appendMessage(
    new ConversationId('conv-001'),
    new ConversationMessage(
        id: new MessageId('msg-001'),
        role: ConversationMessageRole::User,
        content: 'Please summarize my refund options.',
        metadata: ['channel' => 'support'],
    ),
);

$manager = app(ConversationContextManager::class);
$context = $manager->buildContext($conversation->id);
~~~

Run orchestrated multi-agent flows through the package agent orchestrator:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;

$result = app(AgentOrchestrator::class)->run(
    new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle a support refund workflow',
        input: ['subscription_id' => 'sub-123'],
    ),
);
~~~

Use vector storage through the package contract to keep embeddings and semantic search behind a stable boundary:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

$vectorStore = app(VectorStoreInterface::class);

$vectorStore->upsert('support', [
    new VectorDocument(
        id: 'doc-001',
        embedding: [0.8, 0.2, 0.1],
        metadata: ['topic' => 'refunds'],
    ),
]);

$results = $vectorStore->search(
    'support',
    new VectorSearchQuery(
        embedding: [0.9, 0.1, 0.0],
        limit: 3,
        filter: ['topic' => 'refunds'],
    ),
);
~~~

## Testing

The package includes package-owned fakes for runtime, provider policy, tool execution, conversation storage, vector storage, and orchestration. These can be bound directly into the Laravel
container for deterministic tests.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;

$fakeRuntime = new FakeAiRuntime([
    new ExecutionResult(
        runId: 'run-test-001',
        output: 'Fake runtime output',
        provider: 'openai',
        model: 'gpt-test',
    ),
]);

app()->instance(AiRuntime::class, $fakeRuntime);
app()->instance(AgentOrchestrator::class, new FakeAgentOrchestrator());
~~~

The package also exposes assertion helpers and Pest expectations for common fake-driven flows. See `CONTRIBUTING.md` and the package test suite for usage patterns.

## Security and Privacy Defaults

- Tool execution is default-deny unless tools are explicitly registered and authorized.
- Conversation persistence is package-owned and can be kept in memory, Redis, or encrypted database storage.
- Retention-based purging is explicit and available through a command and queue job.
- Telemetry is redacted by default and emits metadata-only package events.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.