# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub CI](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/ci.yml?branch=main&label=ci&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

CI runs **PHP 8.3–8.5** against **Laravel 12 and 13** ([docs/github-ci-matrix.md](docs/github-ci-matrix.md)).

Laravel AI Agent Kit is a Laravel package that delivers a structured agent-workflow toolkit built on top of the official Laravel AI SDK. It provides provider abstraction, pipeline orchestration,
queued execution, and package foundations for building AI-powered application flows safely and predictably.

Maintainers map Laravel AI SDK features to this package in [docs/laravel-ai-sdk-capability-matrix.md](docs/laravel-ai-sdk-capability-matrix.md). **Async jobs** are summarized in [docs/sdk-async-inventory.md](docs/sdk-async-inventory.md).

**Guides (deep dives):** [Configuration](docs/configuration.md) · [Orchestration and blueprints](docs/orchestration-and-blueprints.md) · [Pipelines, queues, memory, vectors](docs/pipelines-queues-and-memory.md) · [Testing with fakes](docs/testing-with-fakes.md)

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

Laravel AI Agent Kit requires the official Laravel AI SDK at runtime. The package declares `laravel/ai` as a Composer dependency, so Composer installs the SDK when you require this package.

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

## Minimal configuration

After publishing `config/ai-agent-kit.php`, ensure at least one **enabled** provider exists, `default_provider` references it, and `failover_order` includes that provider. The default memory driver is **`in_memory`** (non-persistent; fine for tests and local use). For production drivers, queues, vectors, and tool defaults, see the [Production checklist](#production-checklist) and [docs/configuration.md](docs/configuration.md).

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

**Next:** multi-agent flows, blueprints, pipelines, memory, vectors, and extended config — [docs/orchestration-and-blueprints.md](docs/orchestration-and-blueprints.md), [docs/pipelines-queues-and-memory.md](docs/pipelines-queues-and-memory.md), [docs/configuration.md](docs/configuration.md), [docs/testing-with-fakes.md](docs/testing-with-fakes.md).

## Production checklist

Before going live with Agent Kit:

- **Memory driver:** `memory.default_driver` = `in_memory` is process-local; use `database` or `redis` for shared or durable conversation state across workers. Optional: enable `ephemeral_driver_warnings` to log when in-memory drivers are selected in configured environments (default: off).
- **Vector driver:** `vector.default_driver` = `in_memory` is process-local; use `database` or a custom `VectorStoreInterface` binding for shared retrieval. Built-in stores enforce **one embedding width per namespace** on `upsert`. `DatabaseVectorStore::search` is **O(n)** in table rows for the namespace; optional `vector.database.max_scan_rows` bounds reads (approximate top-K). See [docs/laravel-ai-sdk-capability-matrix.md](docs/laravel-ai-sdk-capability-matrix.md).
- **Tool authorizer:** Replace `DenyAllToolAuthorizer` with a policy that allows only the tools and provider tools you intend to expose.
- **Encryption:** Conversation payloads use `Encrypter` when database encryption is enabled; ensure `APP_KEY` and deployment practices match your threat model.
- **Queues:** Queued pipelines serialize `RunContext` on the job; keep `input` / `state` / `metadata` small and avoid embedding full `Conversation` graphs when a `conversationId` suffices. See [Configuration — Queued pipelines and `RunContext`](docs/configuration.md#queued-pipelines-and-runcontext) and optional `pipeline.queued.debug_payload_guard` for local debugging.

Before tagging releases, maintainers follow [docs/release-verification.md](docs/release-verification.md).

## Security and Privacy Defaults

- Tool execution is default-deny unless tools are explicitly registered and authorized.
- Conversation persistence is package-owned and can be kept in memory, Redis, or encrypted database storage.
- Retention-based purging is explicit and available through a command and queue job.
- Telemetry is redacted by default and emits metadata-only package events.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
