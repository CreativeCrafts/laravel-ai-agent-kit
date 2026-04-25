## Context

The kit currently exposes one path through the `laravel/ai` SDK: text-agent prompting via `AnonymousAgent::prompt()`. The runtime layer (`SdkAiRuntime`) accepts an `ExecutionRequest` where the prompt is a bare `string`, there is no schema, no attachment channel, no knobs for temperature/max_tokens/etc., and tools are restricted to the `InMemoryToolRegistry` (custom tools implementing the kit's own `Tool` contract). The SDK itself supports all of these concerns; they are unreachable from consumer code.

This change reshapes the text-execution surface once — deliberately bundling four feature additions into a single breaking-change window so consumers migrate the request/result value objects only one time. Phase 1+2 of a seven-phase roadmap agreed during exploration. Later phases introduce new sibling runtimes per modality (transcription, embeddings, image, reranking), streaming, middleware composition, and conversation-store convergence; those are explicitly out of scope here.

Current call shape:

```
LaravelAiAgentKit::prompt('name')
  ->withVariables([...])
  ->usingProvider('openai')
  ->withTools(['search'])
  ->withTimeout(30)
      │
      ▼
PromptBlueprint → PromptBlueprintCompiler → ExecutionRequest
      │
      ▼
SdkAiRuntime::execute()
  → RuntimeTelemetryAgent (extends AnonymousAgent)
  → AnonymousAgent::prompt(prompt: string, provider, model, timeout)
      │
      ▼
AgentResponse → ExecutionResult { output: string, usage, metadata }
```

## Goals / Non-Goals

**Goals:**
- Expose SDK generation parameters through a typed `GenerationOptions` value object.
- Allow `ExecutionRequest` to carry an optional structured-output schema and have `SdkAiRuntime` route through `StructuredAnonymousAgent` when one is present.
- Allow `ExecutionRequest` to carry a list of `Laravel\Ai\Files\File` attachments on the user message for the current call.
- Expose the SDK's native provider tools (`WebSearch`, `WebFetch`, `FileSearch`, `ProviderTool`, `SimilaritySearch`) as a first-class, separately-registered family — distinct from custom tools, but uniformly governed by the same `ToolAuthorizer` contract.
- Absorb all breaking changes to request/result value objects in a single phase.
- Preserve backward-compatible behaviour when new fields are left null/empty: current consumers who do not set options, schema, attachments, or provider tools get the same runtime behaviour as today.

**Non-Goals:**
- Streaming (`StreamableAgentResponse`, SSE, `BroadcastAgent`) — Phase 5.
- New sibling runtimes for transcription, embeddings, image generation, reranking — Phases 3–4. `AiRuntime` here remains text-only; it will be renamed `TextRuntime` later when the sibling contracts are introduced.
- Adopting the SDK's `HasMiddleware` pipeline — Phase 7.
- Implementing `Laravel\Ai\Contracts\ConversationStore` on kit stores — Phase 6.
- Migrating `TextToStructuredEvaluation` to consume the new `structuredOutput` payload — follow-up patch once this surface lands.
- Persisting attachments across conversation turns — explicitly scoped to the current call.

## Decisions

### D1. `GenerationOptions` is a readonly value object routed through `HasProviderOptions`

A single `GenerationOptions` carries the knobs the SDK can honour at runtime. It is attached to `PromptBlueprint` via `withGenerationOptions(GenerationOptions|null)` and flows through `PromptBlueprintCompiler` into `ExecutionRequest::$generationOptions`. Our telemetry agent classes (see D5) implement `Laravel\Ai\Contracts\HasProviderOptions`; at call time they merge `GenerationOptions` into the provider-options map the driver receives.

**Constraint discovered in the spike (F2).** `Laravel\Ai\Gateway\TextGenerationOptions::forAgent($agent)` reads `#[Temperature]`, `#[MaxTokens]`, `#[MaxSteps]` from the agent's **class attributes** via reflection. There is no runtime-passable channel for those three knobs on `Promptable::prompt()`. The only runtime-configurable path is `HasProviderOptions::providerOptions(Lab|string): ?array` — an instance method the provider driver consults. Runtime-configurable generation knobs therefore must route through that map.

**Fields on `GenerationOptions`** (narrowed from the original 8): `temperature: ?float`, `maxTokens: ?int`, `maxSteps: ?int`, `providerOptions: array<string,mixed>`. Dropped: `topP`, `stopSequences`, `seed`, `frequencyPenalty`, `presencePenalty` — if a caller wants these they are written directly into `providerOptions` under the provider's own key (e.g., `['top_p' => 0.9]` for OpenAI). Less magic, no provider-lookup table for the kit to maintain.

**Rationale.** A value object keeps the surface typed and discoverable. Narrowing to only the knobs the SDK conceptually exposes avoids lying about support for parameters the SDK has no opinion on.

**Alternative considered:** keep all 8 typed fields and silently map the five unsupported ones into `providerOptions` with a provider-key lookup table. Rejected — requires maintaining per-provider key translations (e.g., OpenAI uses `top_p`, Anthropic uses `top_p` too, but other edges vary), and consumers who care about those knobs should be explicit about the provider anyway.

**Alternative considered (and rejected):** loose `array<string,mixed>` on `ExecutionRequest`. Rejected — breaks type safety, kills PHPStan coverage, leaks provider-specific string keys into the kit's public API.

**Documented limitation.** Whether `temperature` / `maxTokens` / `maxSteps` placed into the `providerOptions` map are honoured by the provider driver is driver-specific. For OpenAI / Anthropic / other mainline providers, they are. For edge drivers, behaviour may vary.

### D2. Schema on `ExecutionRequest` accepts `Closure|ObjectSchema|class-string<HasStructuredOutput>|null`

Three shapes, all normalized internally into the `Closure` that `StructuredAnonymousAgent` actually consumes:

| Input | Normalization |
|---|---|
| `Closure` | Passed through unchanged. Signature: `fn (JsonSchema $js): array<string, Type>` |
| `ObjectSchema` | Wrapped in a closure that returns the `ObjectSchema`'s properties (adapter is ~5 LOC in the runtime) |
| `class-string<HasStructuredOutput>` | Resolved via `app()->make()`, verified `instanceof HasStructuredOutput`, wrapped in a closure that calls the instance's `schema(JsonSchema)` method |

When non-null, the runtime instantiates `Laravel\Ai\StructuredAnonymousAgent` (via our `StructuredRuntimeTelemetryAgent`, see D5) instead of `AnonymousAgent`.

**Constraint discovered in the spike (F1).** `StructuredAnonymousAgent::__construct(string, iterable, iterable, Closure)` expects a `Closure`, which it wraps in `SerializableClosure` internally. The closure receives an `Illuminate\Contracts\JsonSchema\JsonSchema` factory and must return `array<string, Illuminate\JsonSchema\Types\Type>`. The kit's `HasStructuredOutput` contract mirrors this shape (`schema(JsonSchema): array<string, Type>`). The SDK's `ObjectSchema` is a *separate* declarative-schema construct, not what `StructuredAnonymousAgent` accepts directly.

**Rationale.** Three shapes keep ergonomics while adding no schema logic of our own — the SDK's `JsonSchema` factory owns type construction. Closure for inline/ad-hoc authoring; `ObjectSchema` for callers who already use the SDK's declarative schema API; class-string for reusable container-resolvable definitions (consistent with how the kit handles tool and prompt names).

**Alternative considered:** accept only `Closure`. Rejected — `ObjectSchema` is a common declarative form in SDK-native code, and class-string resolution is a kit convention consumers already expect.

**Alternative considered:** JSON Schema array. Rejected — would duplicate the SDK's schema concerns in the kit and invite divergence.

**Runtime routing:**

```
ExecutionRequest.schema
      │
  null│           non-null (Closure | ObjectSchema | class-string)
      ▼              ▼
RuntimeTelemetry     StructuredRuntimeTelemetryAgent
Agent                  (wraps the schema in a Closure)
      │              │
      ▼              ▼
AgentResponse    StructuredAgentResponse
  .text          .text + .structured (public array property)
      │              │
      ▼              ▼
ExecutionResult  ExecutionResult
  output=text      output=text
  structured=null  structured=$response->structured
```

### D3. `ExecutionResult` keeps `output: string` non-nullable; adds `structuredOutput: array<string, mixed>|null`

`output` stays populated (from `$response->text`) for both structured and unstructured calls — the SDK's `StructuredAgentResponse` exposes both the structured payload and the raw text. Consumers who today read `->output` keep working. Structured consumers read `->structuredOutput`, which is always an associative array when present.

**Constraint discovered in the spike (F3).** `StructuredAgentResponse::$structured` is a public `array` property assigned in the constructor. It is always an array when the structured path was taken. Typing `structuredOutput` as `mixed|null` would be strictly weaker than reality and would lose PHPStan's ability to help consumers at the callsite.

**Rationale.** Zero break for today's string-reading consumers. Additive, precisely-typed accessor for new consumers. Avoids a discriminated-union result type that would force every caller to check the shape.

**Alternative considered:** make `output` nullable and require consumers to pick between `output`/`structuredOutput`. Rejected — breaks every current consumer for no gain.

**Alternative considered:** type as `mixed|null`. Rejected — the SDK's shape guarantees `array<string, mixed>`; broader typing just discards information.

### D4. Attachments are scoped to the current call; `$attachments: list<Laravel\Ai\Files\File>`

`ExecutionRequest::$attachments` accepts SDK `File` subtypes directly (`Base64Image`, `LocalAudio`, `RemoteDocument`, `ProviderImage`, `StoredDocument`, …). `SdkAiRuntime` passes them as part of the user message construction; the conversation memory bridge is unchanged.

**Rationale.** SDK types are rich, well-shaped, and already understood by providers. Wrapping them in a kit-owned abstraction would be noise. Scoping to the current call avoids confronting attachment-aware conversation persistence — a large design problem deferred to Phase 6.

**Limitation documented in proposal.** Attachments are not persisted across conversation turns in this phase. `RuntimeConversationMemoryBridge` continues to project string messages only. If a consumer attaches an image on turn 1 and calls continue-conversation on turn 2, the provider will see the turn-1 image was part of the prior exchange only if the provider itself retains the session (some do via `conversationId`); the kit's own memory layer will not replay it.

### D5. Two tool registries, two `ExecutionRequest` fields, one authorizer; plus a telemetry-agent split

**Tools.** Custom tools (`Tool` contract, `InMemoryToolRegistry`) and provider tools (SDK classes under `Laravel\Ai\Providers\Tools\`, new `ProviderToolRegistry`) are registered separately and referenced via separate request fields: `$toolNames` (unchanged) and `$providerToolNames` (new). `SdkAiRuntime` materializes both families and passes them to the agent.

**Telemetry agents (constraint discovered in the spike, F5).** The runtime currently constructs `RuntimeTelemetryAgent extends AnonymousAgent`. Since `StructuredAnonymousAgent` is a *different subclass* of `AnonymousAgent`, we cannot drive structured calls through the existing telemetry agent. The clean refactor:

- Extract `EmitsRuntimeTelemetry` trait from `RuntimeTelemetryAgent` — the current telemetry behaviour lives in the trait.
- Extract `CarriesGenerationOptions` trait — implements `Laravel\Ai\Contracts\HasProviderOptions` by merging a `GenerationOptions` instance into the provider-options map.
- `RuntimeTelemetryAgent extends AnonymousAgent` — `use EmitsRuntimeTelemetry`, `use CarriesGenerationOptions`, `implements HasProviderOptions`.
- `StructuredRuntimeTelemetryAgent extends StructuredAnonymousAgent` — same traits, same interface.
- `SdkAiRuntime` picks one class or the other based on whether `ExecutionRequest::$schema` is null.

```
                          ┌──────────────────────────────────┐
                          │  Laravel\Ai\AnonymousAgent        │
                          └──────────────┬──────────────────┘
                                         │
                                    (SDK extends)
                                         │
                          ┌──────────────▼──────────────────┐
                          │  Laravel\Ai\StructuredAnonymous  │
                          │  Agent (adds Closure schema)     │
                          └──────────────┬──────────────────┘
                                         │
         ┌───────────────────────────────┼───────────────────────────────┐
         │                                                               │
  (kit extends)                                                  (kit extends)
         │                                                               │
┌────────▼─────────┐                                       ┌─────────────▼──────────┐
│ RuntimeTelemetry │                                       │ StructuredRuntime      │
│ Agent            │                                       │ TelemetryAgent         │
│                  │                                       │                        │
│ use EmitsRuntime │                                       │ use EmitsRuntime       │
│     Telemetry    │                                       │     Telemetry          │
│ use CarriesGen   │                                       │ use CarriesGen         │
│     Options      │                                       │     Options            │
│ implements HasP  │                                       │ implements HasP        │
│     roviderOpts  │                                       │     roviderOpts        │
└──────────────────┘                                       └────────────────────────┘
```

**Rationale.** The two families have different execution semantics: custom tools execute locally in PHP, provider tools execute server-side on the model provider. Conflating them under one registry hides this and creates surprising authorization edge cases. Separate fields also make it trivial for consumers to audit "this call uses only server-side tools" or "this call uses only local tools."

Both families flow through the same `ToolAuthorizer` contract, widened to take a discriminant:

```php
interface ToolAuthorizer
{
    public function authorizeCustomTool(string $name, AuthorizationContext $ctx): bool;
    public function authorizeProviderTool(string $name, AuthorizationContext $ctx): bool;
}
```

`DenyAllToolAuthorizer` denies both. Consumers with an existing `ToolAuthorizer` implementation get a BC break here — documented in `UPGRADE.md` with a trivial migration (split the single method into two).

**Alternative considered:** single method with a `ToolKind` enum parameter. Rejected — method-per-family makes policy declarations clearer at the call site and removes one branch from consumer implementations.

### D6. `ProviderToolRegistry` stores factory closures, not instances

Provider tools are stateful in a shallow sense (they carry configuration like allowed domains for `WebFetch` or store IDs for `FileSearch`), but they are otherwise cheap to reconstruct per call. Registering factory closures keeps the registry immutable and avoids shared-state leaks across calls.

```
ProviderToolRegistry::register(
    name: 'web-search.tier-1',
    factory: fn () => new WebSearch(allowedDomains: ['docs.example.com'])
)
```

### D7. `PromptBlueprint` builder gains four chainable methods; constructor is considered internal

`PromptBlueprint::withGenerationOptions`, `withSchema`, `withAttachment` / `withAttachments`, `addProviderTool` / `withProviderTools`. The constructor gains matching optional parameters. The package already treats the `PromptBlueprint` constructor as internal (every test uses named arguments via the static builder), so the positional break has low real-world impact — but `UPGRADE.md` notes it anyway.

### D8. `AgentKitManager` gains `run(PromptBlueprint): ExecutionResult`

`AgentKitManager` gains a fourth constructor dependency (`BlueprintRunner`) and a new `run()` method that delegates to it. The `AgentKit` facade gains a matching `@method` annotation.

**Rationale.** `CLAUDE.md` instructs consumers to "prefer injecting `AgentKitManager` directly over using the facade." Today, the manager exposes only the three pre-built workflow operations (`evaluateText`, `evaluateAudio`, `orchestrate`). Single-prompt execution — the primitive being reshaped by this whole phase — is unreachable through the recommended injection point. Consumers must inject `BlueprintRunner` separately, contradicting the package's own guidance. Adding one three-line pass-through method closes that gap.

**Alternatives considered:**
- *Leave `AgentKitManager` alone; document `BlueprintRunner` as the single-prompt entry point.* Rejected — inconsistent with the stated preference for the manager, and creates a second top-level injection point for what should be one coherent surface.
- *Add a richer method like `runPrompt(string $name, Closure $configure): ExecutionResult` that constructs the blueprint internally.* Rejected — the current `LaravelAiAgentKit::prompt(...)` static builder is the idiomatic construction path; the manager should not duplicate it.
- *Defer to a follow-up proposal.* Rejected — `AgentKitManager`'s constructor shape changes here regardless (new dependency), and bundling the method now avoids a second breaking window.

**Pattern implication.** This establishes the convention that **every primary execution path gets a manager method**. Later phases follow suit: Phase 3 adds `transcribe()`, Phase 4 adds `generateEmbeddings()`/`generateImage()`/`rerank()`, Phase 5 adds `stream()`. Setting the pattern in Phase 1+2 keeps the manager coherent as new modalities land instead of growing piecemeal.

```
                        AgentKitManager (after this phase)
   ┌──────────────────────────────────────────────────────────┐
   │ Single-prompt execution (NEW)                            │
   │   • run(PromptBlueprint) → ExecutionResult               │
   │                                                          │
   │ Pre-built workflow execution                             │
   │   • evaluateText(Req) → Result                           │
   │   • evaluateAudio(Req) → Result                          │
   │                                                          │
   │ Orchestration                                            │
   │   • orchestrate(Req) → Result                            │
   │                                                          │
   │ Accessors (unchanged)                                    │
   │   • textToStructuredEvaluation() / audioToTextToEvalu… / │
   │     orchestrator()                                       │
   └──────────────────────────────────────────────────────────┘
```

## Risks / Trade-offs

- [**StructuredAnonymousAgent API drift**] The kit instantiates a new SDK class it hasn't used before; argument shape and message-construction expectations differ subtly from `AnonymousAgent`. → **Mitigation**: a spike during implementation to call `StructuredAnonymousAgent::prompt()` with a representative schema, verify the response shape, and codify the findings as scenarios in `text-execution` spec. Allocate a task for this spike explicitly.
- [**ToolAuthorizer break surprises consumers**] Any production consumer with a custom authorizer implementation will fail PHP's interface-implementation check on upgrade. → **Mitigation**: ship `AbstractToolAuthorizer` as a convenience base class that implements both methods with a single `authorize(string $kind, string $name)` helper, so migration is a one-line change for consumers that don't distinguish the families.
- [**Attachment + conversation memory invariant**] Consumers may reasonably expect "continue this conversation with a new image" to replay prior text. Today's behaviour does replay prior text; the new behaviour also replays prior text but silently drops prior attachments. → **Mitigation**: document clearly in `PromptBlueprint::withAttachment()` docblock and in `UPGRADE.md`. Add a scenario in `text-execution` spec asserting the documented behaviour.
- [**Schema resolution error surface**] Class-string schemas can fail at resolution time (class missing, doesn't implement `HasStructuredOutput`, constructor requires args). → **Mitigation**: wrap resolution in a dedicated `SchemaResolutionException` with a FailureCategory and clear messages; add scenarios for each failure mode.
- [**Provider-tool authorization semantics**] Consumers may expect `DenyAllToolAuthorizer` to allow provider tools (since the consumer's app isn't executing them) — but provider tools are still billable, rate-limited, and leak data to the provider. Denying by default is the safe choice. → **Mitigation**: state this explicitly in the authorizer's class docblock; add a scenario in `tool-authorization` spec.
- [**Positional-arg ExecutionRequest callers**] Any consumer constructing `ExecutionRequest` positionally breaks. → **Mitigation**: search the kit itself for positional calls (expected: zero — the kit uses named args consistently), and document the break prominently in `UPGRADE.md` with a sed-friendly find/replace.

## Migration Plan

1. Ship `UPGRADE.md` at the package root with three sections: "ExecutionRequest constructor," "ToolAuthorizer contract," "ExecutionResult structured output."
2. Release notes emphasize named-argument construction of `ExecutionRequest` and `PromptBlueprint`.
3. Provide `AbstractToolAuthorizer` so authorizer implementations can upgrade with a one-line change.
4. The kit's own `TextToStructuredEvaluation` blueprint is intentionally **not** migrated to consume `structuredOutput` in this change — that's a follow-up patch to minimize review surface.
5. No rollback strategy is needed beyond normal composer version pinning; the change is contained within the kit's own code.

## Open Questions

- Should `GenerationOptions` be extensible at the kit level (e.g., a subclass mechanism), or is the `providerOptions` free-form map sufficient? → **Assumed sufficient** for this phase; revisit only if a concrete use case surfaces.
- Does `StructuredAnonymousAgent::prompt()` accept the same `provider`, `model`, `timeout` arguments as `AnonymousAgent::prompt()`? → **RESOLVED (spike, task 1).** Yes. `StructuredAnonymousAgent extends AnonymousAgent`, so it inherits the `Promptable` trait's `prompt(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null)` unchanged.
- Should the `ToolAuthorizer` split happen in this phase, or be deferred as a separate BC change later? → **Locked to this phase.** Bundling breaks into one window is the whole point.

## Spike findings (Task 1) — resolved

The Task 1 spike against `vendor/laravel/ai/src/` surfaced five misalignments between our initial assumptions and the SDK's actual shape. All are absorbed into the decisions above; this section is a historical record.

| Finding | Affected decision | Resolution |
|---|---|---|
| **F1** `StructuredAnonymousAgent` takes a `Closure`, not an `ObjectSchema` or class-string | D2 | Schema type widened to `Closure\|ObjectSchema\|class-string<HasStructuredOutput>\|null`; runtime normalizes all three into the required closure |
| **F2** `#[Temperature]`/`#[MaxTokens]`/`#[MaxSteps]` are read from class attributes via reflection; no runtime channel on `prompt()` | D1 | `GenerationOptions` narrowed to 4 fields (`temperature`, `maxTokens`, `maxSteps`, `providerOptions`) and routed through `HasProviderOptions` on our telemetry agents |
| **F3** `StructuredAgentResponse::$structured` is a public `array` property, not a method | D3 | `ExecutionResult::$structuredOutput` typed as `array<string, mixed>\|null` |
| **F4** `Promptable::prompt(string, array $attachments, ...)` natively supports attachments | D4 | No revision — pass `attachments: $request->attachments` through the existing call |
| **F5** `StructuredAnonymousAgent` is a sibling subclass of `AnonymousAgent`, not a replacement | D5 | Extract `EmitsRuntimeTelemetry` + `CarriesGenerationOptions` traits; introduce `StructuredRuntimeTelemetryAgent` as sibling to `RuntimeTelemetryAgent` |
