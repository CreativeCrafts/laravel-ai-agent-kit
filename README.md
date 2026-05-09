# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub CI](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/ci.yml?branch=main&label=ci&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package for building AI-powered application workflows on top of the official Laravel AI SDK. It gives your app package-owned blueprints, agents, provider profiles, tools, memory, queues, vector retrieval, and redacted telemetry without exposing provider SDK details as your public workflow API.

Use it when you want Laravel-native AI workflows that are explicit, testable, and safe by default.

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

Publish the Laravel AI SDK configuration and migrations:

~~~bash
php artisan vendor:publish --provider="Laravel\\Ai\\AiServiceProvider"
~~~

Publish this package's configuration and migrations:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
~~~

Optionally publish views:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-views"
~~~

## Minimal configuration

After publishing `config/ai-agent-kit.php`, configure at least one enabled provider profile. The default local/test setup can use the bundled `null` provider profile. Production apps should configure real Laravel AI provider credentials through Laravel AI and map package provider profiles to the capabilities your workflows need.

At minimum, make sure:

- `providers` contains at least one enabled provider profile.
- `default_provider` references an enabled provider profile.
- `failover_order` includes the default provider profile.
- `memory.default_driver` is intentional. The default `in_memory` driver is process-local and non-persistent.
- tool execution remains default-deny until you register and authorize tools deliberately.

See [Configuration](docs/configuration.md) and [Providers](docs/providers.md) for the full setup path.

## Quick start: evaluate text

Prefer dependency injection in controllers, jobs, commands, and application services:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EvaluateSupportReplyController
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

For route-level experiments or concise examples, the `AgentKit` facade exposes the same application-facing workflow shortcuts:

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
~~~

## Quick start: evaluate audio

Use the audio blueprint when the workflow should transcribe audio and evaluate the transcript through one package-owned result shape:

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
~~~

See [Blueprints](docs/blueprints.md) for request fields, result fields, prompt requirements, and structured-output behavior.

## Quick start: register and orchestrate agents

Register first-class agents explicitly through the package registry:

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

Then start an orchestrated workflow:

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

See [Agents and orchestration](docs/agents-and-orchestration.md) for agent definitions, delegation, handoffs, provider-profile assignment, and trace semantics.

## Core concepts

| Concept | What it gives you | Guide |
|--------|--------------------|-------|
| Provider profiles | Capability-based provider selection and failover | [Providers](docs/providers.md) |
| Blueprints | Ready-made workflows such as text and audio evaluation | [Blueprints](docs/blueprints.md) |
| Agents | Package-owned multi-agent workflow participants | [Agents and orchestration](docs/agents-and-orchestration.md) |
| Prompts | Versioned templates and explicit variables | [Prompts](docs/prompts.md) |
| Tools | Explicit registration, schema validation, and authorization | [Tools](docs/tools.md) |
| Memory | Conversation continuation with in-memory, database, or Redis drivers | [Memory](docs/memory.md) |
| Pipelines and queues | Structured sync or queued execution with `RunContext` | [Pipelines and queues](docs/pipelines-and-queues.md) |
| Vectors and retrieval | Application-owned vector stores plus provider Files/Stores boundaries | [Vectors and retrieval](docs/vectors-and-retrieval.md) |
| Streaming and modalities | Streaming text, transcription, embeddings, images, reranking, and audio generation | [Streaming and modalities](docs/streaming-and-modalities.md) |
| Testing | Package fakes and deterministic app tests | [Testing](docs/testing.md) |
| Production | Operational defaults and deployment checks | [Production](docs/production.md) |

## Security and privacy defaults

- Tool execution is default-deny unless tools are explicitly registered and authorized.
- Telemetry is redacted by default and emits metadata-oriented package events.
- Conversation persistence is explicit: use `in_memory` for local/test use, `database` for encrypted durable storage, or `redis` for shared ephemeral memory.
- Provider SDK details stay behind package-owned contracts and DTOs.
- Queued workflows, vector stores, and persistent memory require production-specific configuration.

## Documentation

Start with [Getting started](docs/getting-started.md), then move to the focused guide for the subsystem you need. Maintainer and contributor process documents live behind [CONTRIBUTING.md](CONTRIBUTING.md).

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
