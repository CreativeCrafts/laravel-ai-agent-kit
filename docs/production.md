# Production

Review this checklist before running real workloads with Agent Kit.

## Providers

- Configure real Laravel AI provider credentials outside the package config.
- Map provider profiles to package capabilities such as `text_generation`, `structured_output`, and `audio_transcription`.
- Keep model names and provider-specific options inside provider profile `options`.
- Configure failover order intentionally.

See [Providers](providers.md).

## Tools

- Keep tool execution default-deny until you register tools deliberately.
- Replace the default deny-all authorizer with an application policy that checks user, tenant, workflow, and tool context.
- Validate tool inputs before execution.
- Keep tool outputs bounded and structured.

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
- Enable the debug payload guard in local development if you need serialized job size checks.

See [Pipelines and queues](pipelines-and-queues.md).

## Vectors and retrieval

- Do not use `in_memory` vector storage for shared production retrieval.
- Use the database vector driver or a custom `VectorStoreInterface` implementation for shared storage.
- Keep one embedding width per namespace.
- Use separate namespaces for different embedding models or dimensions.
- Understand that database vector search may scan rows in a namespace; configure scan limits when appropriate.

See [Vectors and retrieval](vectors-and-retrieval.md).

## Telemetry

- Listen to package events for workflow, runtime, failover, files/stores, and streaming observability.
- Keep telemetry redacted by default.
- Use package failure categories in alerts and dashboards.
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
- verify telemetry payloads contain only safe operational metadata

## Recommended rollout

Start with one blueprint or one agent workflow, observe telemetry and failure behavior, then expand tool, memory, vector, and queued execution surfaces as needed.
