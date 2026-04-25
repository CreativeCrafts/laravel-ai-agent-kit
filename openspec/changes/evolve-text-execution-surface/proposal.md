## Why

The kit today is a thin wrapper over a narrow slice of the `laravel/ai` SDK — only the text-agent path, and only the subset of that path that fits through `ExecutionRequest::$prompt: string`. Four capabilities the SDK already ships are unreachable from the kit's public surface: generation parameters (temperature, max_tokens, etc.), structured output (`ObjectSchema` / `HasStructuredOutput`), multimodal input (image/audio/document attachments), and native provider tools (`WebSearch`, `WebFetch`, `FileSearch`). All four reshape the same value objects (`PromptBlueprint`, `ExecutionRequest`, `ExecutionResult`), so landing them together breaks the public shape **once** rather than four times. This is Phase 1+2 of a seven-phase SDK-coverage roadmap agreed during exploration.

## What Changes

- **BREAKING** `ExecutionRequest` gains four optional constructor parameters: `generationOptions`, `schema`, `attachments`, `providerToolNames`. Positional-argument consumers must migrate to named arguments.
- **BREAKING** `ExecutionResult` gains a `structuredOutput` field alongside `output`. Structured calls populate the typed payload; unstructured calls leave it null and keep `output` as today.
- Add `GenerationOptions` readonly value object — `temperature`, `maxTokens`, `topP`, `stopSequences`, `seed`, `frequencyPenalty`, `presencePenalty`, `providerOptions` (free-form map for provider-specific knobs).
- Add `ExecutionAttachment` and the builder surface to attach `Laravel\Ai\Files\File` instances (`Base64Image`, `LocalAudio`, `RemoteDocument`, `ProviderImage`, `StoredDocument`, etc.) to a single call.
- Schema accepts either `Laravel\Ai\ObjectSchema` or `class-string<Laravel\Ai\Contracts\HasStructuredOutput>`. Class-strings resolve via the container.
- `SdkAiRuntime` routes through `Laravel\Ai\StructuredAnonymousAgent` when a schema is present; otherwise continues using `AnonymousAgent`.
- Add `ProviderToolRegistry` (distinct from `InMemoryToolRegistry`). Custom and provider tools remain separate registries with separate `ExecutionRequest` fields.
- `ToolAuthorizer` contract extends to cover provider tools. Default `DenyAllToolAuthorizer` denies both families.
- `PromptBlueprint` gains fluent builder methods: `withGenerationOptions`, `withSchema`, `withAttachment`, `withAttachments`, `addProviderTool`, `withProviderTools`.
- `PromptBlueprintCompiler` and `PromptExecutionMapper` thread new fields through to `ExecutionRequest`.
- **BREAKING** `AgentKitManager` gains a fourth constructor dependency (`BlueprintRunner`) and a new `run(PromptBlueprint): ExecutionResult` method so single-prompt execution with the new capabilities is reachable through the package's recommended top-level injection point. Positional-constructor consumers of `AgentKitManager` must migrate (expected: none — it is container-resolved in practice).
- `AgentKit` facade gains a matching `@method static ExecutionResult run(PromptBlueprint $blueprint)` annotation. README gains a `AgentKit::run(...)` snippet to document the new entry point.
- `FakeAiRuntime` extended to record and assert against options, schema, attachments, and provider-tool names.
- New Pest expectations: `toHaveGenerationOptions`, `toHaveStructuredOutput`, `toHaveAttachmentOfType`, `toHaveRequestedProviderTool`.
- `UPGRADE.md` at package root documenting every breaking change with before/after diffs.

## Capabilities

### New Capabilities
- `text-execution`: The request/response surface of `SdkAiRuntime` — how `ExecutionRequest` is structured, which SDK agent is selected (plain vs structured), how generation options, attachments, and tool-family selection are threaded into the SDK call, and what `ExecutionResult` returns.
- `prompt-blueprint`: The consumer-facing fluent builder (`LaravelAiAgentKit::prompt(...)`) — which call-site knobs are available and how they compile into an `ExecutionRequest`.
- `tool-authorization`: The two tool families (custom, provider) and the `ToolAuthorizer` contract that governs per-request access to both.

### Modified Capabilities
<!-- None — openspec/specs/ is currently empty. All three specs above are new. -->

## Impact

- **Code.** `src/Core/Runtime/` (ExecutionRequest, ExecutionResult, SdkAiRuntime, PromptBlueprintCompiler, RuntimeTelemetryAgent, RuntimeTelemetryContext), `src/Blueprints/PromptBlueprint.php`, `src/Prompts/PromptExecutionMapper.php`, `src/Tools/` (SdkToolMaterializer, new ProviderToolRegistry), `src/Contracts/Tools/ToolAuthorizer.php`, `src/Contracts/Core/AiRuntime.php` (no method signature change but behaviour documented), `src/Support/AgentKitManager.php` (new `run()` method, 4th ctor dep), `src/Facades/AgentKit.php` (new `@method` annotation), `src/LaravelAiAgentKitServiceProvider.php` (manager binding updated), `src/Testing/Fakes/FakeAiRuntime.php`, `tests/Pest.php` (new expectations), `tests/AgentKitFacadeTest.php` (new delegation test).
- **Public API.** Positional-constructor consumers of `ExecutionRequest` will break. `ExecutionResult::$output` remains non-null for unstructured calls (no consumer break). `PromptBlueprint` additions are additive at the builder surface but break positional-constructor callers (unusual — constructor is not intended for direct use).
- **Dependencies.** None new. All required SDK surfaces already ship in `laravel/ai: ^0.6`.
- **Deferred to later phases.** Streaming (Phase 5), new modality runtimes — transcription/embeddings/image/reranking (Phase 3–4), SDK middleware adoption (Phase 7), `ConversationStore` convergence (Phase 6), `TextToStructuredEvaluation` blueprint adoption of structured output (follow-up patch after this surface lands).
- **Downstream simplification (out of scope but noted).** Once the follow-up patch migrates `TextToStructuredEvaluationSpecialistAgent` to pass a schema and consume `$runtimeResult->structuredOutput`, `src/Blueprints/Support/StructuredEvaluationOutputNormalizer.php` (~400 lines of JSON-extraction, refusal heuristics, payload-shape repair) becomes largely redundant. Reviewers should see this arc: Phase 1+2 creates the primitive, the follow-up patch collapses the parsing workaround.
- **Documented limitation.** Attachments are scoped to the current call only in this phase. The conversation memory bridge (`RuntimeConversationMemoryBridge`) assumes string messages today; persisting attachments across turns is out of scope and will be addressed when the conversation store contract is revisited.
