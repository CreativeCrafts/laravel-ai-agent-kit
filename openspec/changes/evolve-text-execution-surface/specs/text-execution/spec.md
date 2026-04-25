## ADDED Requirements

### Requirement: ExecutionRequest carries optional generation options

`ExecutionRequest` SHALL accept an optional `GenerationOptions` value object exposing four fields: `temperature: ?float`, `maxTokens: ?int`, `maxSteps: ?int`, and `providerOptions: array<string, mixed>`. The kit's telemetry agent classes SHALL implement `Laravel\Ai\Contracts\HasProviderOptions` so that, at prompt time, non-null typed fields and every entry from `providerOptions` are merged into the single provider-options map the provider driver receives. When `generationOptions` is `null`, the merged map MUST be empty and `HasProviderOptions::providerOptions()` MUST return `null` so the provider applies its own defaults.

#### Scenario: Request without generation options returns null provider options
- **WHEN** an `ExecutionRequest` is constructed with `generationOptions = null` and executed by `SdkAiRuntime`
- **THEN** the telemetry agent's `providerOptions(...)` method MUST return `null`, causing the SDK call to apply provider defaults for `temperature`, `maxTokens`, `maxSteps`, and any provider-specific knobs

#### Scenario: Typed fields are merged into providerOptions
- **WHEN** an `ExecutionRequest` carries a `GenerationOptions` with `temperature = 0.2`, `maxTokens = 512`, `maxSteps = null`, `providerOptions = []`
- **THEN** the telemetry agent's `providerOptions(...)` method MUST return a map containing `'temperature' => 0.2` and `'maxTokens' => 512`, and MUST NOT include a `maxSteps` entry

#### Scenario: providerOptions map entries are preserved and merged
- **WHEN** an `ExecutionRequest` carries a `GenerationOptions` with `temperature = 0.1` and `providerOptions = ['top_p' => 0.9]`
- **THEN** the telemetry agent's `providerOptions(...)` method MUST return a map containing both `'temperature' => 0.1` and `'top_p' => 0.9`

#### Scenario: providerOptions entries take precedence over typed fields on key collision
- **WHEN** an `ExecutionRequest` carries a `GenerationOptions` with `temperature = 0.1` and `providerOptions = ['temperature' => 0.9]`
- **THEN** the telemetry agent's `providerOptions(...)` method MUST return a map containing `'temperature' => 0.9` (the explicit `providerOptions` entry wins, giving callers an escape hatch for provider-specific semantics)

### Requirement: ExecutionRequest carries an optional structured-output schema

`ExecutionRequest` SHALL accept an optional `schema` field that is one of three shapes: a `Closure` matching the signature `fn (Illuminate\Contracts\JsonSchema\JsonSchema $js): array<string, Illuminate\JsonSchema\Types\Type>`, a `Laravel\Ai\ObjectSchema` instance, or a class-string of a class implementing `Laravel\Ai\Contracts\HasStructuredOutput`. When `schema` is non-null, `SdkAiRuntime` MUST route the call through `StructuredRuntimeTelemetryAgent` (which extends `Laravel\Ai\StructuredAnonymousAgent`) instead of `RuntimeTelemetryAgent`. When `schema` is `null`, the runtime MUST continue to use `RuntimeTelemetryAgent` with no change from current behaviour. The runtime SHALL normalize all three non-null input shapes into the `Closure` that `StructuredAnonymousAgent` consumes internally.

#### Scenario: Null schema uses the plain telemetry agent
- **WHEN** an `ExecutionRequest` has `schema = null` and is executed
- **THEN** the runtime MUST instantiate `RuntimeTelemetryAgent` (extending `AnonymousAgent`) and return an `ExecutionResult` whose `structuredOutput` is `null`

#### Scenario: Closure schema is passed through unchanged
- **WHEN** an `ExecutionRequest` has `schema` set to a `Closure` matching the `HasStructuredOutput::schema()` signature
- **THEN** the runtime MUST instantiate `StructuredRuntimeTelemetryAgent` with that closure supplied verbatim and return an `ExecutionResult` whose `structuredOutput` is populated from `$response->structured`

#### Scenario: ObjectSchema instance is adapted to a closure
- **WHEN** an `ExecutionRequest` has `schema` set to a non-null `ObjectSchema` instance
- **THEN** the runtime MUST construct a closure that returns the ObjectSchema's property type map, instantiate `StructuredRuntimeTelemetryAgent` with that closure, and return an `ExecutionResult` whose `structuredOutput` is populated from `$response->structured`

#### Scenario: Class-string schema is resolved via the container and adapted to a closure
- **WHEN** an `ExecutionRequest` has `schema` set to a class-string of a class implementing `HasStructuredOutput`
- **THEN** the runtime MUST resolve that class via `app()->make()`, verify the resolved instance implements `HasStructuredOutput`, construct a closure that delegates to the resolved instance's `schema(JsonSchema)` method, and use that closure to drive the structured call

#### Scenario: Class-string that does not implement HasStructuredOutput raises SchemaResolutionException
- **WHEN** an `ExecutionRequest` has `schema` set to a class-string whose resolved instance does not implement `HasStructuredOutput`
- **THEN** the runtime MUST raise a `SchemaResolutionException` before invoking the SDK, with a message naming the offending class

### Requirement: ExecutionRequest carries optional attachments on the current call

`ExecutionRequest` SHALL accept an optional `attachments` list where each element is an instance of `Laravel\Ai\Files\File` (including its concrete subtypes such as `Base64Image`, `LocalAudio`, `RemoteDocument`, `ProviderImage`, `StoredDocument`). `SdkAiRuntime` MUST pass attachments as part of the user message for the current call. Attachments MUST NOT be persisted by `RuntimeConversationMemoryBridge` across conversation turns in this phase.

#### Scenario: Empty attachments list matches current behaviour
- **WHEN** an `ExecutionRequest` has `attachments = []` and is executed
- **THEN** the SDK user message MUST be constructed exactly as it would be today, containing only the string prompt

#### Scenario: Attachments are forwarded on the current call's user message
- **WHEN** an `ExecutionRequest` carries a single `Base64Image` attachment and is executed
- **THEN** the SDK user message for this call MUST include that image alongside the string prompt

#### Scenario: Attachments are not replayed on conversation continuation
- **WHEN** a first `ExecutionRequest` with an attachment is executed with `storeConversation = true`, and a subsequent `ExecutionRequest` with `continueConversation = true` and no new attachments is executed on the same conversation
- **THEN** the second call's projected prior-message history MUST NOT replay the first call's attachment

### Requirement: ExecutionRequest distinguishes custom tools from provider tools

`ExecutionRequest` SHALL carry two independent tool lists: `toolNames: list<string>` (unchanged, resolved via `InMemoryToolRegistry` to local `Tool` implementations) and `providerToolNames: list<string>` (new, resolved via `ProviderToolRegistry` to SDK-native provider tools). `SdkAiRuntime` MUST materialize both families and pass both to the underlying SDK agent.

#### Scenario: Request with only custom tools behaves as today
- **WHEN** an `ExecutionRequest` has `toolNames = ['search']` and `providerToolNames = []`
- **THEN** only the custom `search` tool MUST be materialized and passed to the SDK agent

#### Scenario: Request with only provider tools uses the provider registry
- **WHEN** an `ExecutionRequest` has `toolNames = []` and `providerToolNames = ['web-search.default']`
- **THEN** only the provider tool registered under `web-search.default` MUST be materialized from `ProviderToolRegistry` and passed to the SDK agent

#### Scenario: Request with both families passes both to the agent
- **WHEN** an `ExecutionRequest` has `toolNames = ['search']` and `providerToolNames = ['web-search.default']`
- **THEN** both tools MUST be materialized from their respective registries and both MUST be passed to the SDK agent

### Requirement: ExecutionResult exposes a structuredOutput accessor

`ExecutionResult` SHALL expose a new `structuredOutput: array<string, mixed>|null` field alongside the existing `output: string`. `output` MUST remain populated from the SDK response's text for both structured and unstructured calls. `structuredOutput` MUST be `null` for unstructured calls and MUST be populated by reading the public `$structured` property of the SDK's `StructuredAgentResponse` for structured calls.

#### Scenario: Unstructured call leaves structuredOutput null
- **WHEN** an `ExecutionRequest` with `schema = null` is executed and produces a response
- **THEN** the returned `ExecutionResult` MUST have `output` equal to the SDK response text and `structuredOutput` equal to `null`

#### Scenario: Structured call populates structuredOutput from $response->structured
- **WHEN** an `ExecutionRequest` with a non-null `schema` is executed and produces a `StructuredAgentResponse`
- **THEN** the returned `ExecutionResult` MUST have `output` equal to `$response->text` and `structuredOutput` equal to `$response->structured` (the public array property on `StructuredAgentResponse`)
