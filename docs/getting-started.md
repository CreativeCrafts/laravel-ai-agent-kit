# Getting started

This guide gets a Laravel app from install to the first package-owned AI workflow.

## 1. Install the package

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

The package depends on the official Laravel AI SDK and uses it as the runtime substrate. Your application code should normally use Agent Kit workflows, contracts, DTOs, and fakes rather than provider SDK payloads directly.

## 2. Publish configuration and migrations

Publish Laravel AI configuration and migrations first:

~~~bash
php artisan vendor:publish --provider="Laravel\\Ai\\AiServiceProvider"
~~~

Then publish Agent Kit configuration and migrations:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
~~~

## 3. Configure a provider profile

Open `config/ai-agent-kit.php` and make sure:

- `providers` contains at least one enabled profile with the capabilities your workflows need. The bundled `null` profile has empty capabilities and is not sufficient for blueprint evaluation on its own — merge a preset from `examples/provider-profile-presets.php` or configure real profiles.
- `default_provider` points at an enabled profile.
- `failover_order` includes the default profile.
- each workflow capability you need is represented in a compatible provider profile.

The default `null` provider is useful for bootstrapping and deterministic package tests. Production and blueprint workflows should use real Laravel AI provider configuration and capability-bearing profiles.

See [Providers](providers.md) for provider profiles, presets, capabilities, and failover.

## 4. Run your first text evaluation

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;

final class EvaluateReply
{
    public function __construct(
        private TextToStructuredEvaluation $evaluation,
    ) {
    }

    public function __invoke(string $reply): array
    {
        $result = $this->evaluation->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'support reply',
                text: $reply,
                enabledDimensions: ['clarity', 'accuracy', 'completeness'],
                promptVersion: '1.0.0',
            ),
        );

        return $result->toArray();
    }
}
~~~

The blueprint returns a package-owned result DTO. It does not expose provider-native response payloads as your application contract.

## 5. Choose your next guide

- Use [Blueprints](blueprints.md) for text/audio evaluation workflows.
- Use [Agents and orchestration](agents-and-orchestration.md) for custom multi-agent workflows.
- Use [Tools](tools.md) before exposing tool execution.
- Use [Memory](memory.md) before persisting or continuing conversations.
- Use [Pipelines and queues](pipelines-and-queues.md) for long-running work.
- Use [Production](production.md) before deploying real workloads.

## Agent Kit versus direct Laravel AI SDK usage

Use Agent Kit when your workflow needs package-owned behavior:

- provider profiles and runtime failover
- blueprints, orchestration, or queued pipelines
- conversation memory, retention, encryption, or attachment replay
- package tool authorization and schema validation
- redacted package events and typed failure categories
- package-owned fakes and deterministic workflow tests

Use the Laravel AI SDK directly when the application intentionally wants SDK-native behavior outside the Agent Kit envelope:

- SDK queue jobs or broadcast-agent behavior exactly as shipped by Laravel AI
- provider-specific experimental options not promoted to Agent Kit DTOs
- SDK-only files/stores/vector-provider experiments
- tests that specifically validate Laravel AI SDK behavior rather than package contracts

Agent Kit does not mirror every SDK class. Gaps are classified in maintainer parity docs as package-owned, direct-SDK, deferred, or out of scope.

## Safety defaults to know early

- Tool execution is denied by default until you register and authorize tools.
- Telemetry is redacted by default.
- The default memory and vector drivers are in-memory and process-local.
- Queued pipelines serialize `RunContext`; keep payloads small and explicit.
