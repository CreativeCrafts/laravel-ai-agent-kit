# Errors and telemetry

Agent Kit normalizes failures and emits redacted package events so applications can observe workflows without depending on provider-native payloads.

## Failure categories

Package failures use stable categories such as:

- `execution_failed`
- `provider_failure`
- `provider_transport`
- `provider_overloaded`
- `rate_limited`
- `authentication_failed`
- `quota_exceeded`
- `invalid_request`
- `unsupported_capability`
- `configuration_failure`
- `tool_authorization_denied`
- `conversation_failure`
- `budget_exceeded`
- `refusal`
- `malformed_output`
- `invalid_output`
- `provider_profile_mismatch`
- `failover_policy_error`
- `logical_failure`

Use these package categories for operational handling. Do not treat provider-native exception taxonomies as the public workflow contract.

## Runtime failures

Runtime failures are wrapped in typed package exceptions where possible. Previous exceptions are preserved for debugging while package telemetry remains redacted. Classification makes four independent decisions: category, provider health, retryability, and failover safety. Only provider-health failures increment circuit breakers, and only failover-safe failures consume another provider profile. Unknown failures fail closed in the default strict mode.

## Blueprint failures

Blueprint-owned structured-output behavior normalizes refusal, malformed output, and invalid output into package terms. This keeps application code focused on package DTOs and typed package failures.

## Orchestration failures

Orchestration failures carry safe context such as orchestration ID, agent key, provider profile, failure category, and bounded error text. They do not expose raw prompt text or provider-native payloads.

## Redaction defaults

Telemetry should expose safe operational metadata such as:

- run IDs and orchestration IDs
- provider profile names
- Laravel AI provider instance names where recorded
- model identifiers where appropriate
- tool names
- input and metadata key lists
- counts, lengths, and status flags
- sanitized exception class and message details

Telemetry should avoid sensitive payload values, raw prompt bodies, raw file bodies, credential material, and provider-native diagnostic payloads.

## Runtime attempt metadata

Text runtime results include profile-oriented and SDK-oriented attempt metadata:

- `runtime_provider_attempts` / `runtime_final_provider` — Agent Kit provider profiles
- `runtime_sdk_provider_attempts` / `runtime_final_sdk_provider` — Laravel AI provider instances

Circuit-breaker keys use the profile identity (`providers.<profile-name>`), not the driver.

`LaravelAiFilesService` and `LaravelAiStoresService` emit redacted gateway operation events. The events include operation name, provider, resource identifiers, success status, and bounded error text when applicable.

Disable these events in tests only when global event assertions need a narrower event set:

~~~php
'observability' => [
    'laravel_ai_files_stores' => [
        'enabled' => false,
    ],
],
~~~

## Streaming telemetry

Streaming events emit redacted chunk and terminal metadata. Broadcast payloads should contain safe identifiers, counts, and lengths rather than generated content.

## Operational guidance

- Listen for package events, not provider SDK events, in application monitoring.
- Keep metadata values safe and compact.
- Prefer package failure categories in alerts and dashboards.
- Treat raw provider payloads as internal diagnostics, not application contracts.

See [Production](production.md) for deployment checks.
