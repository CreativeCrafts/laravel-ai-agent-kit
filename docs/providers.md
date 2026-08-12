# Providers

Agent Kit has three provider identities. They can share the same string, but they must not be treated as the same thing:

| Identity | Meaning | Example |
|----------|---------|---------|
| **Provider profile** | Agent Kit policy/configuration identity. Used for failover, circuit breakers, telemetry, profile models, and profile options. | `primary-image-scorer` |
| **SDK provider** | Named Laravel AI provider instance from `config/ai.php`. This is what the SDK call receives. | `openai-production` |
| **Driver** | Underlying Laravel AI / provider driver. | `openai` |

Application code should reason in package terms such as `text_generation`, `structured_output`, and `audio_transcription`, not provider-native payload shapes.

## Provider profile basics

A profile is the array key under `ai-agent-kit.providers`. Each profile must declare a non-empty `driver`. Optional `sdk_provider` names the Laravel AI provider instance. When `sdk_provider` is omitted, Agent Kit uses `driver`.

~~~php
'providers' => [
    'primary-image-scorer' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-production',
        'enabled' => true,
        'capabilities' => [
            'text_generation',
            'structured_output',
            'vision',
        ],
        'options' => [
            'model' => 'gpt-example',
            'provider_options' => [
                'reasoning' => [
                    'effort' => 'medium',
                ],
            ],
        ],
    ],
],

'default_provider' => 'primary-image-scorer',

'failover_order' => [
    'primary-image-scorer',
],
~~~

Configure the matching Laravel AI provider instance separately:

~~~php
// config/ai.php
'providers' => [
    'openai-production' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
    ],
],
~~~

The package validates that the default provider profile exists, is enabled, and appears in failover order.

Do not assume the profile name is a Laravel AI provider name. Agent Kit resolves the selected provider profile to a Laravel AI provider instance before invoking the SDK.

## Capability-based selection

Blueprints and agents declare the capabilities they need. A provider profile is eligible only when it is enabled and satisfies those capabilities.

Common capabilities include:

- `text_generation`
- `structured_output`
- `audio_transcription`
- `embeddings`
- `image_generation`
- `image_input`
- `vision`
- `reranking`
- `audio_generation`

Capabilities are independent. `structured_output` does not imply `text_generation`. Workflows that generate structured text must declare both.

Use capability names to express workflow needs. Keep model names in `options.model` and provider-native defaults in `options.provider_options`.

## Runtime provider resolution

Resolution is deterministic:

1. An explicit Agent Kit profile name on the request, if registered in `ProviderRegistry`.
2. Otherwise the configured Agent Kit default profile, when the request omits a provider.
3. An unregistered name is treated as a direct Laravel AI provider instance name for backwards compatibility.

`failover_order` does **not** decide whether a profile exists. An explicitly selected profile resolves even when it is absent from failover order. Failover membership only controls whether a later attempt is eligible after a provider-edge failure.

SDK provider resolution:

1. Profile `sdk_provider` when configured
2. Otherwise the profile `driver`

Model resolution:

1. Explicit request model
2. Profile `options.model`
3. Laravel AI provider default

Direct runtime, blueprint, and orchestration paths all flow through the same runtime binding, so provider resolution is centralized. Transcription, embeddings, image generation, audio generation, and reranking use the same profile-to-SDK translation.

## Generation options

Typed generation controls and provider-native options are separate channels.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;

new GenerationOptions(
    temperature: 0.2,
    maxTokens: 16383,
    maxSteps: 4,
    providerOptions: [
        'openai' => [
            'reasoning' => [
                'effort' => 'medium',
            ],
        ],
        'anthropic' => [
            'thinking' => [
                'budget_tokens' => 2048,
            ],
        ],
    ],
);
~~~

Typed fields (`temperature`, `maxTokens`, `maxSteps`) are forwarded as Laravel AI agent methods. Laravel AI performs provider-specific translation, for example mapping `maxTokens` to OpenAI `max_output_tokens`. Agent Kit does not rename those fields itself.

Raw `providerOptions` are the provider-native channel. Prefer maps keyed by Laravel AI provider instance name or driver. Unscoped maps are preserved for backwards compatibility and apply to every attempt.

Merge precedence for raw options on one attempt:

1. Request-level option for the current SDK provider or driver
2. Current profile `options.provider_options`
3. Laravel AI / provider default

Provider-native options are resolved per attempt. OpenAI-specific options are not forwarded onto an Anthropic failover attempt.

## Structured output strictness

`ExecutionRequest::$strictStructuredOutput` defaults to `false`. Set it to `true` when the consumer needs Laravel AI strict structured output. `PromptBlueprint::withStrictStructuredOutput()` and `AudioImageStructuredEvaluationRequest::$strictStructuredOutput` forward the same flag.

## Instructions

Request instructions are forwarded exactly, concatenated with projected conversation system instructions. If the result is empty, Agent Kit sends no synthetic system instruction.

Optional package-level default instructions are opt-in through `runtime.default_instructions`. They are used only when the request and projected conversation supply none.

## Failover

`failover_order` defines the deterministic provider **profile** traversal order:

~~~php
'failover_order' => [
    'openai-structured',
    'anthropic-structured',
],
~~~

For prompt execution, provider-edge failures are retried against the next eligible profile in `failover_order`. Agent Kit preserves the request schema, strictness, tools, provider tools, attachments, timeout, typed generation options, memory projection, and metadata across attempts. Raw provider options are rebuilt for the next profile.

When no later profile remains eligible, the runtime surfaces a package-owned `RuntimeExecutionException` with provider-failure category and emits failover exhaustion/resolution telemetry.

Streaming uses a conservative policy: failover is creation-only. If the provider stream cannot be created before any chunks are emitted, the runtime may try the next profile. Once chunks are emitted, mid-stream provider errors become one terminal `StreamFailure`; the runtime does not replay the partial stream against another profile.

## Circuit breaker integration

When circuit-breaker failover filtering is enabled, profiles with open breakers are skipped by the failover selector. Runtime attempts record success/failure against `providers.<profile-name>` so independent profiles that share a driver do not collapse into one breaker.

## Runtime telemetry

Successful and exhausted attempts include profile-oriented metadata:

- `runtime_provider_attempts`
- `runtime_final_provider`

and SDK identity:

- `runtime_sdk_provider_attempts`
- `runtime_final_sdk_provider`

Profile names remain the policy identity. SDK provider names are the Laravel AI instances that were actually invoked.

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
