# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub CI](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/ci.yml?branch=main&label=ci&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package that delivers a structured agent-workflow toolkit built on top of the official Laravel AI SDK. It provides provider abstraction, pipeline orchestration,
queued execution, and package foundations for building AI-powered application flows safely and predictably.

Maintainers track **SDK ↔ package coverage** in [docs/laravel-ai-sdk-capability-matrix.md](docs/laravel-ai-sdk-capability-matrix.md) so Laravel AI capabilities map to runtime, pipelines, orchestration, budgets, and memory in one place.

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
want encrypted persistent storage and retention-based purging, or to `redis` when you need shared ephemeral memory across workers. Optional **`memory.laravel_ai_legacy`** reads Laravel AI’s default `agent_*` tables when your package store has no row (database driver only; see `UPGRADE.md`). Optional **`memory.attachments_replay`** gates replay of persisted attachment references on continued conversations (opt-in per request via `metadata['attachment_replay']`; see `UPGRADE.md`).

Retry and circuit breaker resilience settings are configured explicitly under `resilience`. Retry policy evaluation is bounded by `budgets.max_retries_per_step` and is now enforced by the synchronous
pipeline runner at execution time.

Pipeline execution now enforces both `budgets.max_steps` and `budgets.max_total_timeout_seconds` with typed budget exceptions.

Runtime execution now enforces `budgets.max_tokens` and `budgets.max_tool_calls` using SDK usage/tool-call telemetry.

Optional **runtime middleware** wraps every `AiRuntime::execute` call (including blueprints and orchestration): register ordered class names under `runtime.middleware` in `config/ai-agent-kit.php`. Each class must implement `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware`. Implement `TerminatingRuntimeMiddleware` when you need a reverse-order hook after a successful response.

**Streaming text** uses the Laravel AI SDK stream path for non-schema requests. Inject `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime` and iterate `executeStream($request)` to receive `StreamChunk` values (ordered `text_delta` segments) followed by a single terminal `StreamComplete` or `StreamFailure`. Structured-output requests (`ExecutionRequest::$schema` set) are not supported for streaming; use `execute()` instead. Optional Echo broadcast: set `runtime.streaming.broadcast_channel` (or per-request metadata `streaming_broadcast_channel`) to a **public** channel name; the package dispatches `RuntimeStreamChunkEmitted`, `RuntimeStreamCompleted`, and `RuntimeStreamFailed` with **redacted** payloads (lengths, keys, identifiers — no prompt text).

**Modality runtimes** (transcription, embeddings, image generation, reranking, **audio generation**) use contracts under `CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\`. The default driver is `sdk`, which bridges to Laravel AI (`Transcription`, `Embeddings`, `Image`, `Reranking`, `Audio`). Override per modality with `modalities.<name>.default_driver` in `config/ai-agent-kit.php` (`sdk` or a class implementing the contract). The `AudioToTextToEvaluation` blueprint calls the transcription runtime when `audio_reference` is raw base64 or a `data:*;base64,...` URI; opaque references (for example `s3://...`) still use the registered prompt plus `AiRuntime`.

`budgets.max_cost_usd` is enforced in fail-closed mode: when configured, each runtime request must provide numeric `metadata.cost_usd` (or `metadata.estimated_cost_usd`) so cost ceilings can be
validated deterministically.

Circuit breaker state can be applied to failover selection by enabling `resilience.circuit_breaker.apply_to_failover` (opt-in to preserve previous defaults).

Prompt repositories support two drivers via `prompts.default_driver`:

- `in_memory` (default)
- `file` (loads prompt metadata and templates from `resources/prompts` or `prompts.file.root_path`)

Pipeline lifecycle and failover telemetry are emitted through Laravel events with redacted defaults. Event payloads expose safe metadata such as run identifiers, provider names, step classes, counts,
and key lists rather than raw prompt, input, metadata, or provider option values by default.

### Adopting features from the roadmap

The package ships several optional subsystems (structured evaluation, middleware, streaming, modalities, legacy conversation read bridge, attachment replay). **`CHANGELOG.md`** lists them under *Rollout* with the recommended order (Phases 1–6, plus Phase 0 hardening in parallel). **`UPGRADE.md`** has migration notes per phase. GitHub Actions workflows live under [`.github/workflows/`](https://github.com/creativecrafts/laravel-ai-agent-kit/tree/main/.github/workflows); the README CI badge targets [`ci.yml`](https://github.com/creativecrafts/laravel-ai-agent-kit/actions/workflows/ci.yml) on `main`.

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
            'apply_to_failover' => false,
            'failure_threshold' => 3,
            'reset_timeout_seconds' => 60,
            'half_open_success_threshold' => 1,
        ],
    ],

    'runtime' => [
        'middleware' => [
            // \App\Runtime\LogAiRuntimeRequest::class,
        ],
        'streaming' => [
            'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
        ],
    ],

    'modalities' => [
        'transcription' => ['default_driver' => 'sdk'],
        'embeddings' => ['default_driver' => 'sdk'],
        'image_generation' => ['default_driver' => 'sdk'],
        'reranking' => ['default_driver' => 'sdk'],
        'audio_generation' => ['default_driver' => 'sdk'],
    ],

    'prompts' => [
        'default_driver' => 'in_memory',

        'file' => [
            'root_path' => null,
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

        // Optional: read Laravel AI default `agent_*` tables when package store misses (see UPGRADE.md).
        // 'laravel_ai_legacy' => [
        //     'enabled' => true,
        //     'connection' => null,
        //     'conversations_table' => 'agent_conversations',
        //     'messages_table' => 'agent_conversation_messages',
        // ],

        // Optional: attachment replay policy when continuing conversations (see UPGRADE.md).
        // 'attachments_replay' => [
        //     'enabled' => false,
        //     'max_per_turn' => null,
        //     'max_age_seconds' => null,
        //     'allow_provider_references' => true,
        //     'deny_types' => ['base64-image', 'base64-document', 'base64-audio', 'local-image', 'local-document', 'local-audio'],
        //     'deny_url_substrings' => [],
        // ],
    ],
];
~~~

### Tool input schema support (in-memory registry)

`InMemoryToolRegistry` intentionally validates a constrained, deterministic schema subset for runtime tool input validation:

- Root schema must be `type: object`.
- `properties` must be a top-level object map.
- Each property must declare one supported `type`: `string`, `integer`, `number`, `boolean`, `array`, or `object`.
- `required` must be a list of declared property names.
- `additionalProperties` may be set to `true` or `false`.

Nested JSON Schema features (for example nested `properties`, `items`, `oneOf`, or format/pattern constraints) are currently out of scope for the in-memory validator.

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

For package-facing workflows, prefer dependency injection in controllers, jobs, commands, or application services. Direct container resolution is still appropriate for infrastructure and advanced
extension points, but it should not be the default teaching style for common workflow execution.

### Injection-first workflow usage

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupportReplyEvaluationController
{
    public function __invoke(Request $request, TextToStructuredEvaluation $evaluation): JsonResponse
    {
        $result = $evaluation->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'support reply',
                text: $request->string('text')->toString(),
                enabledDimensions: ['clarity', 'accuracy', 'completeness'],
                promptVersion: '1.0.0',
            ),
        );

        return response()->json($result->toArray());
    }
}
~~~

### AgentKit facade shortcuts

The `AgentKit` facade is an optional convenience surface for application-facing workflow calls. Package internals and advanced extension points should continue to prefer dependency injection and
explicit contracts.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$textResult = AgentKit::evaluateText(
    new TextToStructuredEvaluationRequest(
        subject: 'support reply',
        text: 'We can refund the unused portion of your subscription within five business days.',
        enabledDimensions: ['clarity', 'accuracy', 'completeness'],
        promptVersion: '1.0.0',
    ),
);

$audioResult = AgentKit::evaluateAudio(
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        audioMimeType: 'audio/wav',
        enabledDimensions: ['clarity', 'accuracy'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
    ),
);

$orchestrationResult = AgentKit::orchestrate(
    new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle a support refund workflow',
        input: ['subscription_id' => 'sub-123'],
    ),
);

// Single-prompt execution with the new request surface
// (generation options, structured output, attachments, provider tools).
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;

$result = AgentKit::run(
    LaravelAiAgentKit::prompt('package.followup-summary')
      ->withVariable('topic', 'refund window')
      ->withSchema(\App\Schemas\FollowUpSummary::class)
);
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

## Multi-Agent Orchestration

The package orchestration boundary is package-owned and provider-neutral:

- callers submit one `OrchestrationRequest` to `AgentOrchestrator`
- agents implement package contracts and return package-owned `AgentExecutionResult` values
- provider selection is resolved from each agent's declared provider profiles and required capabilities
- delegation policy and handoff semantics are enforced by the orchestrator rather than by individual agents
- the final `OrchestrationResult` exposes one orchestration ID, one final owner, one final output payload, one summary, and a lineage trace

For the full architecture, authoring model, delegation and handoff rules, provider-profile assignment behavior, and flagship workflow guidance, see
[`MULTI_AGENT_ORCHESTRATION.md`](MULTI_AGENT_ORCHESTRATION.md).

Run the package-owned `TextToStructuredEvaluation` blueprint when you want one structured evaluation result from one orchestration call while keeping the internal coordinator-to-specialist flow hidden
behind the public API:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateText(
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

- `structuredEvaluationPath` (`structured_output` when the runtime returned typed structured output, or `text_normalization` when the kit fell back to parsing model text)
- `structuredEvaluationRepaired` (true when the text fallback path repaired wrapped or embedded JSON)

The enabled dimensions are caller-configurable, but the top-level result contract remains package-owned and stable.

Before running the blueprint, register the prompt template referenced by `promptName` and `promptVersion`. The specialist stage requests structured output from the runtime using a package-owned JSON
schema; if the provider does not populate structured output, the kit falls back to the same bounded text normalization used previously.

Run the package-owned `AudioToTextToEvaluation` blueprint when you want one orchestration call to transcribe audio first and then evaluate the resulting transcript through the same structured
evaluation pipeline:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateAudio(
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        audioMimeType: 'audio/wav',
        enabledDimensions: ['clarity', 'accuracy'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
    ),
);

$transcript = $result->transcript;
$summary = $result->summary;
~~~

The audio blueprint returns a fixed package-owned result schema that extends the text evaluation shape with audio-specific fields:

- `audioReference`
- `transcript`
- `summary`
- `recommendedAction`
- `confidence`
- `enabledDimensions`
- `dimensions` keyed by dimension name, each with `score`, `summary`, and `evidence`
- `transcriptionPromptName`, `transcriptionPromptVersion`, `evaluationPromptName`, and `evaluationPromptVersion`
- `orchestrationSummary` and `finalAgent`

Provider profiles for the audio blueprint must be compatible with both stages:

- the transcription stage requires a provider profile that supports `audio_transcription`
- the evaluation stage requires a provider profile that supports `structured_output`

Register both prompt templates before execution. The transcription stage returns one transcript string (plain text from the runtime, not a separate structured-output schema). The evaluation
stage uses the same structured evaluation path as `TextToStructuredEvaluation` (runtime schema plus bounded text fallback).

Build and run a synchronous pipeline with typed steps through dependency injection:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final class NormalizePipelineService
{
    public function __construct(
        private PipelineRunner $runner,
    ) {
    }

    public function run(): RunContext
    {
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

        return $this->runner->run(
            $pipeline,
            new RunContext(
                runId: 'run-001',
                input: ['text' => 'Hello world'],
            ),
        );
    }
}
~~~

Dispatch a queued pipeline using a typed pipeline definition and injected dispatcher:

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

final class QueueTranscriptNormalizationCommand
{
    public function __construct(
        private QueuedPipelineDispatcher $dispatcher,
    ) {
    }

    public function __invoke(): void
    {
        $this->dispatcher->dispatch(
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
    }
}
~~~

Use conversation memory through injected package-owned context manager and store contracts:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use DateTimeImmutable;

final class SupportConversationService
{
    public function __construct(
        private ConversationStore $store,
        private ConversationContextManager $contextManager,
    ) {
    }

    public function appendUserMessage(): void
    {
        $conversationId = new ConversationId('conv-001');
        $timestamp = new DateTimeImmutable();

        $conversation = $this->store->find($conversationId);

        if ($conversation === null) {
            $conversation = new Conversation(
                id: $conversationId,
                createdAt: $timestamp,
                updatedAt: $timestamp,
            );
        }

        $conversation = $conversation->withAppendedMessage(
            new ConversationMessage(
                id: new MessageId('msg-001'),
                role: ConversationMessageRole::User,
                content: 'Please summarize my refund options.',
                createdAt: $timestamp,
                metadata: ['channel' => 'support'],
            ),
            $timestamp,
        );

        $this->store->save($conversation);
    }

    public function initializeRunContext(RunContext $context): RunContext
    {
        return $this->contextManager->initialize($context);
    }
}
~~~

For runtime-integrated memory (blueprints, `AiRuntime` with `store_conversation` / `continue_conversation`), use `ConversationContextManager` and the `RunContext` pipeline — see `UPGRADE.md` and the in-tree blueprint tests.

Run orchestrated multi-agent flows through the package agent orchestrator:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::orchestrate(
    new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle a support refund workflow',
        input: ['subscription_id' => 'sub-123'],
    ),
);
~~~

`OrchestrationResult` stays package-owned. The stable surface is:

- `orchestrationId` for the whole orchestration run
- `finalAgent` and `finalExecutionId` for the terminal owner and terminal execution node
- `finalOutput` for the package-owned workflow payload
- `summary` for the compact orchestration-level summary
- `trace` for execution lineage, including `executionId`, `parentExecutionId`, `agentKey`, `providerProfile`, `resultKind`, optional `targetAgent`, and safe metadata

Delegation semantics are explicit:

- `delegate_and_resume` sends work to a child agent and then resumes the parent agent after the child finishes
- `transfer_control` hands ownership to the child agent, making the delegated agent the final owner if it completes the workflow

Use vector storage through the injected package contract to keep embeddings and semantic search behind a stable boundary. The default `VectorStoreInterface` binding is **in-memory only**; ship a custom binding (for example Pinecone, pgvector, or an SDK-backed adapter) when you need persistent or shared vector storage.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

final class SupportKnowledgeService
{
    public function __construct(
        private VectorStoreInterface $vectorStore,
    ) {
    }

    public function indexAndSearch(): array
    {
        $this->vectorStore->upsert('support', [
            new VectorDocument(
                id: 'doc-001',
                embedding: [0.8, 0.2, 0.1],
                metadata: ['topic' => 'refunds'],
            ),
        ]);

        return $this->vectorStore->search(
            'support',
            new VectorSearchQuery(
                embedding: [0.9, 0.1, 0.0],
                limit: 3,
                filter: ['topic' => 'refunds'],
            ),
        );
    }
}
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
