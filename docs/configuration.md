# Configuration

Publish the package configuration before editing it:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

The package validates `config/ai-agent-kit.php` during boot by default. Invalid provider, budget, memory, vector, tool, or runtime settings should fail early instead of producing unclear runtime behavior.

## Required provider settings

At minimum, configure:

~~~php
'providers' => [
    'null' => [
        'driver' => 'null',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
    ],
],

'default_provider' => 'null',

'failover_order' => ['null'],
~~~

For real provider profiles, see [Providers](providers.md).

## Validation

~~~php
'validation' => [
    'enabled' => true,
],
~~~

Keep validation enabled in normal application environments. Disable it only for narrow test scenarios where a test intentionally builds partial configuration.

## Budgets

Budgets protect workflows from unbounded execution:

~~~php
'budgets' => [
    'max_steps' => 20,
    'max_tool_calls' => 50,
    'max_retries_per_step' => 2,
    'max_total_timeout_seconds' => 120,
    'max_tokens' => null,
    'max_cost_usd' => null,
],
~~~

Runtime execution enforces token and tool-call ceilings when usage metadata is available. Cost ceilings are fail-closed: when `max_cost_usd` is configured, requests must provide numeric cost metadata so the package can make deterministic decisions.

## Resilience

~~~php
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
~~~

Failover and circuit-breaker behavior are package-owned policies. Provider SDK exceptions should be normalized before they become application-facing workflow behavior.

## Runtime

~~~php
'runtime' => [
    'middleware' => [
        // \App\Runtime\LogAiRuntimeRequest::class,
    ],
    'streaming' => [
        'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
    ],
],
~~~

Runtime middleware wraps package `AiRuntime` execution. Streaming is covered in [Streaming and modalities](streaming-and-modalities.md).

## Memory

~~~php
'memory' => [
    'default_driver' => 'in_memory',
],
~~~

Use `in_memory` for local and test usage, `database` for encrypted durable storage, and `redis` for shared ephemeral memory across workers. See [Memory](memory.md).

## Vectors

~~~php
'vector' => [
    'default_driver' => 'in_memory',
],
~~~

Use `database` or a custom `VectorStoreInterface` implementation for shared retrieval. Built-in stores enforce one embedding width per namespace. See [Vectors and retrieval](vectors-and-retrieval.md).

## Tools

Tool execution remains default-deny until your app registers tools and authorizes execution. Configure packaged tools only after you understand the authorizer path. See [Tools](tools.md).

## Observability

Telemetry events are redacted by default. Files/Stores gateway observability can be toggled through:

~~~php
'observability' => [
    'laravel_ai_files_stores' => [
        'enabled' => true,
    ],
],
~~~

See [Errors and telemetry](errors-and-telemetry.md).

## Related guides

- [Providers](providers.md)
- [Memory](memory.md)
- [Tools](tools.md)
- [Pipelines and queues](pipelines-and-queues.md)
- [Vectors and retrieval](vectors-and-retrieval.md)
- [Production](production.md)
