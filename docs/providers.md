# Providers

Provider profiles describe which configured AI backends can satisfy package workflow capabilities. Application code should reason in package terms such as `text_generation`, `structured_output`, and `audio_transcription`, not provider-native payload shapes.

## Provider profile basics

A profile normally contains:

~~~php
'providers' => [
    'openai-structured' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [
            'model' => env('OPENAI_STRUCTURED_MODEL', 'gpt-4.1'),
        ],
    ],
],

'default_provider' => 'openai-structured',

'failover_order' => ['openai-structured'],
~~~

The package validates that the default provider exists, is enabled, and appears in failover order.

## Capability-based selection

Blueprints and agents declare the capabilities they need. A provider profile is eligible only when it is enabled and satisfies those capabilities.

Common capabilities include:

- `text_generation`
- `structured_output`
- `audio_transcription`
- `embeddings`
- `image_generation`
- `reranking`
- `audio_generation`

Use capability names to express workflow needs. Keep provider-specific model details inside the profile `options` array.

## Runtime provider resolution

When a runtime request omits `provider`, Agent Kit resolves the configured `default_provider` and uses that profile as the first attempt. If the request also omits `model`, the runtime uses the profile `options.model` value when present; otherwise the Laravel AI SDK provider default may apply.

When a runtime request explicitly supplies `provider` and `model`, those values are used for the first attempt. The explicit provider remains eligible for failover only when it appears in `failover_order`.

Direct runtime, blueprint, and orchestration paths all flow through the same runtime binding, so provider resolution is centralized.

## Failover

`failover_order` defines the deterministic provider traversal order:

~~~php
'failover_order' => [
    'openai-structured',
    'anthropic-structured',
],
~~~

For prompt execution, provider-edge failures are retried against the next eligible provider in `failover_order`. Agent Kit preserves the request schema, tools, provider tools, attachments, timeout, generation options, memory projection, and metadata across attempts.

When no later provider remains eligible, the runtime surfaces a package-owned `RuntimeExecutionException` with provider-failure category and emits the existing failover exhaustion/resolution telemetry.

Streaming uses a conservative policy: failover is creation-only. If the provider stream cannot be created before any chunks are emitted, the runtime may try the next provider. Once chunks are emitted, mid-stream provider errors become one terminal `StreamFailure`; the runtime does not replay the partial stream against another provider.

## Circuit breaker integration

When circuit-breaker failover filtering is enabled, providers with open breakers are skipped by the failover selector. Runtime provider attempts also record provider success/failure against `providers.<provider-name>` so breaker state reflects real runtime outcomes.

## Agent-specific provider profiles

Agents can declare a primary profile and fallback profiles through their package-owned `AgentDefinition`:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;

new AgentDefinition(
    key: 'refund.specialist',
    displayName: 'Refund Specialist',
    requiredCapabilities: ['structured_output'],
    primaryProviderProfile: 'openai-structured',
    fallbackProviderProfiles: ['anthropic-structured'],
);
~~~

This keeps provider routing out of workflow controller code.

## Presets

The package includes provider profile examples in `examples/provider-profile-presets.php`. Copy the preset you want into your published `config/ai-agent-kit.php` and adapt model names, credentials, and options for your application.

Example pattern:

~~~php
$presets = require base_path('vendor/creativecrafts/laravel-ai-agent-kit/examples/provider-profile-presets.php');

$preset = $presets['gemini_structured_evaluation'];

return [
    'providers' => $preset['providers'],
    'default_provider' => $preset['default_provider'],
    'failover_order' => $preset['failover_order'],
];
~~~

Merge selectively if your application already has provider profiles.

## Recommended next steps

- Use [Blueprints](blueprints.md) for the shipped text and audio workflows.
- Use [Agents and orchestration](agents-and-orchestration.md) for custom agent routing.
- Review [Production](production.md) before using real provider credentials and persistent memory.
