# Production

Review this checklist before running real workloads with Agent Kit.

## Providers

- Configure real Laravel AI provider credentials outside the package config.
- Map provider profiles to package capabilities such as `text_generation`, `structured_output`, and `audio_transcription`.
- Keep model names and provider-specific options inside provider profile `options`.
- Configure failover order intentionally.
- Ensure every provider that may participate in runtime failover appears in `failover_order`.
- Configure circuit-breaker failover filtering when you want open breakers to skip unhealthy providers.
- Remember that prompt failover can make multiple provider attempts for one application request.
- For streaming, failover is conservative: stream creation can fail over before chunks are emitted; mid-stream failures are terminal and are not replayed against another provider.

See [Providers](providers.md).

## Tools

- Keep tool execution default-deny until you register tools deliberately.
- Replace the default deny-all authorizer with an application policy that checks user, tenant, workflow, and tool context.
- Validate tool inputs before execution.
- Keep tool outputs bounded and structured.
- Use the supported schema subset deliberately; nested objects, arrays, enums, nullable values, and `additionalProperties: false` are enforced before handlers run.

See [Tools](tools.md).

## Memory

- Do not use `in_memory` for durable or cross-worker state.
- Use `database` when conversations must persist and encrypted payload storage is required.
- Use `redis` for shared ephemeral memory across workers.
- Set retention expectations explicitly.
- Avoid storing sensitive values in metadata.

See [Memory](memory.md).

## Queues

- Use queued pipelines for long-running structured workflows.
- Keep `RunContext` payloads small and serializable.
- Prefer `conversationId` over serializing a full `Conversation` graph.
- Configure queue connection, queue name, timeout, and result handler behavior deliberately.
- Enable `debug_payload_guard` in local development if you need serialized job size checks.
- Enable `payload_guard` in production when you want dispatch-time rejection for oversized serialized jobs.
- Set `max_serialized_job_bytes` to a limit that matches your queue backend and operational payload budget.

See [Pipelines and queues](pipelines-and-queues.md).

## Vectors and retrieval

- Do not use `in_memory` vector storage for shared production retrieval.
- Use the database vector driver or a custom `VectorStoreInterface` implementation for shared storage.
- Keep one embedding width per namespace.
- Use separate namespaces for different embedding models or dimensions.
- Understand that database vector search may scan rows in a namespace; configure scan limits when appropriate.
- Database vector writes use an atomic upsert keyed by `namespace` and `document_id` after namespace dimension validation.

See [Vectors and retrieval](vectors-and-retrieval.md).

## Streaming

- Streaming failures are normalized into terminal `StreamFailure` values.
- Provider failures emitted before stream iteration and during stream iteration both produce redacted stream-failure telemetry.
- Stream creation may fail over to the next eligible provider before chunks are emitted.
- Mid-stream failures are terminal and do not trigger replay against another provider.
- Do not expect structured-output schemas from streaming calls; use normal runtime execution for schema-backed requests.

See [Streaming and modalities](streaming-and-modalities.md).

## Telemetry

- Listen to package events for workflow, runtime, failover, files/stores, and streaming observability.
- Keep telemetry redacted by default.
- Use package failure categories in alerts and dashboards.
- Monitor runtime provider attempt metadata and failover exhaustion events when failover is enabled.
- Avoid attaching sensitive values to metadata.

See [Errors and telemetry](errors-and-telemetry.md).

## Configuration validation

Keep configuration validation enabled. It is intended to catch invalid providers, budgets, memory/vector drivers, and runtime options during boot.

## Testing before deploy

Before deploying:

- run your package/application test suite without live provider calls
- verify provider profiles and failover order
- verify tool authorization denies by default and allows only intended paths
- verify memory persistence and retention behavior
- verify queued workflows with the same queue driver shape you use in production
- verify queue payload guard limits when enabled
- verify telemetry payloads contain only safe operational metadata
- verify failover behavior with fakes before enabling multi-provider failover in production

## Recommended rollout

Start with one blueprint or one agent workflow, observe telemetry and failure behavior, then expand tool, memory, vector, queued execution, and provider failover surfaces as needed.
