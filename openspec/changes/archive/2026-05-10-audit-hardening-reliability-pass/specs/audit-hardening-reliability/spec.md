## ADDED Requirements

### Requirement: Streaming runtime normalizes stream creation failures

`StreamingAiRuntime::executeStream()` MUST return package-owned stream terminal values for provider/runtime failures that occur during stream creation, not only for failures that occur during stream iteration.

When stream creation fails, the runtime MUST dispatch redacted stream failure telemetry where an event dispatcher is available, yield a `StreamFailure`, and terminate the generator without leaking the raw provider exception to consumers iterating the stream.

#### Scenario: Provider throws before stream iterator exists

- **WHEN** `SdkAiRuntime::executeStream()` calls the SDK-backed stream method and that call throws before returning a stream object
- **THEN** the runtime MUST yield a `StreamFailure`
- **AND** the failure category MUST be `provider_failure`
- **AND** `RuntimeStreamFailed` MUST be dispatched with redacted context when events are available
- **AND** the raw provider exception MUST be preserved as the previous cause inside the normalized package exception used for telemetry

### Requirement: Custom tool validation is recursive for supported schemas

The package tool registry MUST validate custom tool input recursively for the supported schema subset before executing a tool.

The supported recursive validation subset MUST include:

- scalar property types: `string`, `integer`, `number`, `boolean`;
- array item schemas when `items` is declared;
- nested object `properties`;
- nested `required` lists;
- `additionalProperties: false` at every object level;
- `nullable: true`;
- scalar `enum` values.

Validation errors SHOULD identify the failing property path.

#### Scenario: Nested required field is missing

- **GIVEN** a registered custom tool with a nested object property that declares a required nested field
- **WHEN** the tool is executed with that nested field missing
- **THEN** execution MUST fail before the tool handler is called
- **AND** the validation error MUST identify the nested property path

#### Scenario: Nested additional property is rejected

- **GIVEN** a registered custom tool with `additionalProperties: false` on a nested object
- **WHEN** the tool is executed with an undeclared nested field
- **THEN** execution MUST fail before the tool handler is called

#### Scenario: Array item type is rejected

- **GIVEN** a registered custom tool with an array property whose `items` schema declares a supported type
- **WHEN** the tool is executed with an array item that does not match that type
- **THEN** execution MUST fail before the tool handler is called

#### Scenario: Nullable and enum constraints are enforced

- **GIVEN** a registered custom tool with nullable and enum-constrained properties
- **WHEN** the tool is executed
- **THEN** null MUST be accepted only where `nullable: true` is declared
- **AND** enum-constrained values MUST match one of the declared scalar enum values

### Requirement: Database vector upsert is atomic for document writes

`DatabaseVectorStore::upsert()` MUST perform insert/update writes atomically for rows keyed by `namespace` and `document_id` while preserving namespace embedding-dimension validation.

The implementation MUST avoid an application-level `exists()` then `insert()` race for the same `(namespace, document_id)` pair.

#### Scenario: Existing vector document is upserted

- **GIVEN** a vector document already exists in a namespace
- **WHEN** `DatabaseVectorStore::upsert()` is called with the same document ID and compatible embedding dimensions
- **THEN** the existing row MUST be updated with the new embedding, metadata, and `updated_at`
- **AND** the original `created_at` SHOULD be preserved by the database upsert behavior

#### Scenario: New vector document is upserted

- **GIVEN** a vector document does not exist in a namespace
- **WHEN** `DatabaseVectorStore::upsert()` is called
- **THEN** the row MUST be inserted with namespace, document ID, embedding, metadata, `created_at`, and `updated_at`

### Requirement: Queued pipeline payload guard supports production opt-in

Queued pipeline dispatch MUST support an explicit payload-size guard that can run outside debug mode.

The existing debug-only guard MUST continue to work. The new production-capable guard MUST be disabled by default and enabled by configuration.

#### Scenario: Production payload guard is enabled

- **GIVEN** `app.debug` is false
- **AND** `ai-agent-kit.pipeline.queued.payload_guard` is true
- **AND** a serialized queued pipeline job exceeds `ai-agent-kit.pipeline.queued.max_serialized_job_bytes`
- **WHEN** the pipeline is dispatched
- **THEN** dispatch MUST fail before the job is queued
- **AND** the exception message SHOULD point developers to `docs/pipelines-and-queues.md`

#### Scenario: Debug payload guard remains unchanged

- **GIVEN** `ai-agent-kit.pipeline.queued.debug_payload_guard` is true
- **WHEN** `app.debug` is true
- **THEN** oversized queued pipeline jobs MUST fail before dispatch
- **AND** when `app.debug` is false and the production payload guard is not enabled, the debug guard MUST NOT run
