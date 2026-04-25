## 1. Implementation spike: StructuredAnonymousAgent

- [x] 1.1 Read `vendor/laravel/ai/src/StructuredAnonymousAgent.php` and its parent/traits to confirm constructor and `prompt()` signatures
- [x] 1.2 Verify whether it accepts the same `provider`, `model`, `timeout` arguments as `AnonymousAgent::prompt()`
- [x] 1.3 Confirm the shape of `StructuredAgentResponse` — which accessor exposes the typed payload vs the raw text
- [x] 1.4 Write a throwaway integration test that exercises `StructuredAnonymousAgent` with a toy `ObjectSchema` and records the call shape; delete after findings are captured in design.md — _superseded by source-reading; no test written, findings captured directly in design.md § Spike findings_
- [x] 1.5 Update `design.md` § Open Questions with resolved answers (or record blockers)

## 2. Value objects

- [x] 2.1 Create `src/Core/Runtime/GenerationOptions.php` as a final readonly class with four fields per revised D1 (`?float $temperature`, `?int $maxTokens`, `?int $maxSteps`, `array $providerOptions = []`); constructor validates `temperature` in `[0.0, 2.0]`, `maxTokens >= 1`, `maxSteps >= 1` when non-null
- [x] 2.2 Add `toProviderOptionsMap(): array<string,mixed>` method that merges non-null typed fields into a single map with `providerOptions` entries taking precedence on key collision (per revised spec scenario)
- [x] 2.3 Write Pest unit tests for `GenerationOptions` boundary validation and `toProviderOptionsMap` merge semantics (including the collision precedence rule)

## 3. ExecutionRequest / ExecutionResult surface

- [x] 3.1 Add constructor parameters to `ExecutionRequest`: `?GenerationOptions $generationOptions = null`, `Closure|ObjectSchema|string|null $schema = null`, `list<File> $attachments = []`, `list<string> $providerToolNames = []`
- [x] 3.2 Add constructor validation for `$schema` class-string shape (non-empty, `class_exists`) — deferred runtime check (must implement `HasStructuredOutput`) happens at resolution time, not construction
- [x] 3.3 Add constructor validation for `$attachments` (every entry must be `Laravel\Ai\Files\File` subclass)
- [x] 3.4 Add a `structuredOutput: array<string, mixed>|null` field to `ExecutionResult`; keep `output: string` non-nullable
- [x] 3.5 Update `ExecutionResult` metadata shape documentation to reference structured calls
- [x] 3.6 Update the runtime exceptions namespace with `SchemaResolutionException`

## 4. PromptBlueprint builder

- [x] 4.1 Add constructor parameters for the four new fields on `PromptBlueprint`, all defaulting to null/empty
- [x] 4.2 Implement `withGenerationOptions(?GenerationOptions): self`
- [x] 4.3 Implement `withSchema(ObjectSchema|string|null): self`
- [x] 4.4 Implement `withAttachment(File): self` (append) and `withAttachments(list<File>): self` (replace)
- [x] 4.5 Implement `addProviderTool(string): self` (append) and `withProviderTools(list<string>): self` (replace)
- [x] 4.6 Pest tests: default blueprint has null/empty for all new fields; each builder returns a new instance; receiver remains unchanged after derivation

## 5. PromptBlueprintCompiler / PromptExecutionMapper

- [x] 5.1 Thread the four new blueprint fields through `PromptBlueprintCompiler` into the constructed `ExecutionRequest`
- [x] 5.2 Update `PromptExecutionMapper::mapToExecutionRequest` to accept and forward the four new fields (signature change — acceptable break per Phase 1+2 policy)
- [x] 5.3 Pest tests: full blueprint → request round-trip preserves every new field

## 6. ProviderToolRegistry

- [x] 6.1 Create `src/Contracts/Tools/ProviderToolRegistry.php` interface with `register(string $name, Closure $factory): void`, `has(string $name): bool`, `get(string $name): object` (SDK provider-tool instance), `all(): list<string>`
- [x] 6.2 Create `src/Tools/InMemoryProviderToolRegistry.php` final class implementing the above
- [x] 6.3 Factory invocation MUST produce a new instance on every `get` call
- [x] 6.4 Create `src/Tools/Exceptions/ProviderToolNotRegisteredException.php`
- [x] 6.5 Register `InMemoryProviderToolRegistry` in `LaravelAiAgentKitServiceProvider` as the concrete binding for the contract
- [x] 6.6 Pest tests: factory invoked per-get; unregistered name raises typed exception; namespace isolation from custom tool registry

## 7. ToolAuthorizer contract split

- [x] 7.1 Modify `src/Contracts/Tools/ToolAuthorizer.php`: rename the single method into two (`authorizeCustomTool(Tool, array)`, `authorizeProviderTool(string)`) — signatures differ per family (see revised tool-authorization spec; no `AuthorizationContext` abstraction introduced in this phase)
- [x] 7.2 Create `src/Tools/AbstractToolAuthorizer.php` convenience base class that exposes a single `authorize(ToolKind, string, ?Tool, array): bool` method for consumers that do not distinguish families
- [x] 7.3 Update `src/Tools/DenyAllToolAuthorizer.php` to implement both methods returning `false`
- [x] 7.4 Introduce `src/Tools/ToolKind.php` enum (Custom, Provider)
- [x] 7.5 Pest tests: `DenyAllToolAuthorizer` denies both families; `AbstractToolAuthorizer` routes both callbacks through the single method

## 8. SDK agent routing in SdkAiRuntime

- [x] 8.1 Extract an `AgentFactory` (or equivalent private method) in `SdkAiRuntime` that instantiates either `RuntimeTelemetryAgent` (schema null) or `StructuredRuntimeTelemetryAgent` (schema non-null) — see task 8.4 for the sibling classes
- [x] 8.2 When schema is a class-string, resolve via `app()->make()` and verify `instanceof HasStructuredOutput`; raise `SchemaResolutionException` on failure (class-string, non-class-string non-closure, or instance not implementing the contract)
- [x] 8.3 Normalize schema input into a `Closure`: pass `Closure` verbatim; adapt `ObjectSchema` to a closure returning the ObjectSchema's property type map; adapt a resolved `HasStructuredOutput` instance to a closure that delegates to its `schema(JsonSchema)` method
- [x] 8.4 Introduce telemetry-context interface + traits so both text and structured agents can be recognized by `SdkTelemetryNormalizer`:
  - Create `src/Core/Runtime/CarriesRuntimeTelemetry.php` interface with `telemetryContext(): RuntimeTelemetryContext`
  - Create `src/Core/Runtime/HasRuntimeTelemetryContext.php` trait providing the property and accessor
  - Create `src/Core/Runtime/CarriesGenerationOptions.php` trait implementing `HasProviderOptions::providerOptions()` by delegating to `GenerationOptions::toProviderOptionsMap()`
  - Update `RuntimeTelemetryAgent`: `implements CarriesRuntimeTelemetry, HasProviderOptions`; `use HasRuntimeTelemetryContext, CarriesGenerationOptions`; constructor gains `?GenerationOptions $generationOptions = null`
  - Create `src/Core/Runtime/StructuredRuntimeTelemetryAgent.php` extending `StructuredAnonymousAgent` with the same interfaces and traits
  - Replace all four `instanceof RuntimeTelemetryAgent` checks in `src/Observability/SdkTelemetryNormalizer.php` with `instanceof CarriesRuntimeTelemetry`
- [x] 8.5 Confirm that routing generation options through `HasProviderOptions` reaches the provider driver by inspecting `Laravel\Ai\Providers\Concerns\GeneratesText` (already reads `TextGenerationOptions::forAgent($agent)` which consults provider options); add a regression test verifying the driver receives the merged map — _covered in `tests/RuntimeProviderOptionsTest.php`_
- [x] 8.6 Pest tests: null schema uses `RuntimeTelemetryAgent`; closure schema uses `StructuredRuntimeTelemetryAgent` with closure verbatim; ObjectSchema adapted to closure; class-string resolves via container; class-string not implementing `HasStructuredOutput` raises typed exception; class-string for non-existent class raises typed exception — _covered across `tests/SdkAiRuntimeTest.php`, `tests/StructuredOutputRoutingTest.php`, `tests/SchemaResolutionTest.php`; non-existent class is rejected at `ExecutionRequest` construction time_

## 9. Attachments on the user message

- [x] 9.1 Update the `$agent->prompt(...)` call in `SdkAiRuntime::execute` to pass `attachments: $request->attachments` (SDK's `Promptable::prompt()` natively accepts `array $attachments` — no other wiring needed)
- [x] 9.2 Confirm in `RuntimeConversationMemoryBridge::reconcile` that attachments are NOT persisted (no code change expected; write a regression test) — _covered in `tests/RuntimeAttachmentsTest.php`_
- [x] 9.3 Pest tests: empty attachments match today's behaviour; populated attachments reach the `AgentPrompt` instance passed to the provider; continuation of a conversation does not replay prior attachments — _covered in `tests/RuntimeAttachmentsTest.php`_

## 10. Provider-tool materialization

- [x] 10.1 Create `src/Tools/ProviderToolMaterializer.php` that resolves `providerToolNames` through `ProviderToolRegistry` and consults `ToolAuthorizer::authorizeProviderTool`
- [x] 10.2 Inject both materializers into `SdkAiRuntime`; pass both result sets to the SDK agent
- [x] 10.3 ~~Update `SdkToolMaterializer` (custom tools) to consult `ToolAuthorizer::authorizeCustomTool`~~ — _superseded by spec design: custom-tool authorization happens in `InMemoryToolRegistry::execute()` (closer to the actual invocation, where input is available); provider-tool authorization happens at materialization time (since provider tools execute server-side and can't be intercepted later)_
- [x] 10.4 Both materializers raise `ToolAuthorizationDeniedException` on deny (typed exception, categorized)
- [x] 10.5 Pest tests: custom and provider deny paths each raise typed exception; authorization decisions on one family do not affect the other — _covered in `tests/SdkAiRuntimeTest.php`, `tests/ToolFamilyIsolationTest.php`, `tests/ToolRegistryTest.php`_

## 11. ExecutionResult structured payload

- [x] 11.1 In `SdkAiRuntime::execute`, when the schema path is taken and the response is a `StructuredAgentResponse`, read the public `$response->structured` property and assign it to `ExecutionResult::$structuredOutput`
- [x] 11.2 Unstructured calls (schema null, response is plain `AgentResponse`) leave `structuredOutput` as `null`
- [x] 11.3 Pest tests: unstructured call has `structuredOutput = null`; structured call populated with the array from `$response->structured`; `output` string remains correct in both cases — _covered in `tests/SdkAiRuntimeTest.php` and `tests/FakeAiRuntimeExpectationsTest.php`. Note: SDK fakes normalize responses to `AgentResponse`, dropping the `StructuredAgentResponse` subtype, so structured-fake assertion is documented as `null` while production runtime maps `$response->structured` correctly_

## 12. Testing fakes and expectations

- [x] 12.1 Extend `src/Testing/Fakes/FakeAiRuntime.php` to record `generationOptions`, `schema`, `attachments`, `providerToolNames` per execution — _the fake records the entire `ExecutionRequest` value object, which already exposes the four new fields_
- [x] 12.2 Allow queued `ExecutionResult` fixtures to declare `structuredOutput` — _the result value object already exposes the field_
- [x] 12.3 Add Pest expectations: `toHaveGenerationOptions`, `toHaveStructuredOutput`, `toHaveAttachmentOfType`, `toHaveRequestedProviderTool` in `tests/Pest.php`
- [x] 12.4 Document the new expectations briefly in the test conventions section of `CLAUDE.md` (no public README change — kept internal)

## 13. Service provider wiring

- [x] 13.1 Register `ProviderToolRegistry` in `LaravelAiAgentKitServiceProvider`
- [x] 13.2 Ensure `ProviderToolMaterializer` is container-resolved and injected into `SdkAiRuntime`
- [x] 13.3 Provide a config seam that pre-registers common provider tools out of the box, disabled by default — _wired via `ai-agent-kit.tools.provider_tools` (validated by `ConfigValidator`); supports `web_search`, `web_fetch`, `file_search` types_
- [x] 13.4 Update `config/ai-agent-kit.php` with the new config block; update config validator accordingly

## 14. AgentKitManager wiring for single-prompt execution

- [x] 14.1 Add `BlueprintRunner` as a fourth constructor parameter on `src/Support/AgentKitManager.php`; preserve readonly semantics
- [x] 14.2 Implement `run(PromptBlueprint $blueprint): ExecutionResult` as a pass-through to `BlueprintRunner::run()`
- [x] 14.3 Update the `AgentKitManager` binding in `LaravelAiAgentKitServiceProvider` (around line 153) to resolve and inject `BlueprintRunner`
- [x] 14.4 Add `@method static ExecutionResult run(PromptBlueprint $blueprint)` to the `AgentKit` facade DocBlock (`src/Facades/AgentKit.php`)
- [x] 14.5 Add a `AgentKit::run(...)` snippet to README
- [x] 14.6 Extend `tests/AgentKitFacadeTest.php` with a `run()` delegation test
- [x] 14.7 Update the constructor-resolution test to assert all four dependencies are present

## 15. Upgrade documentation

- [x] 15.1 Create `UPGRADE.md` at package root with sections for: `ExecutionRequest` constructor, `ToolAuthorizer` contract split, `ExecutionResult` structured output accessor, `AgentKitManager` constructor shape
- [x] 15.2 Each section: before/after code snippets, sed-friendly migration hints where applicable
- [x] 15.3 Add `AbstractToolAuthorizer` usage example for consumers upgrading authorizers
- [x] 15.4 For the `AgentKitManager` section: note that container-resolved usage is unaffected; only positional-constructor instantiation breaks

## 16. Quality gates

- [x] 16.1 Run `composer rector-fix`
- [x] 16.2 Run `composer pint`
- [x] 16.3 Run `composer analyse` — clean (no errors)
- [x] 16.4 Run `composer test` — all 396 tests green
- [x] 16.5 Run `composer quality` end-to-end as the final gate

## 17. Review readiness

- [x] 17.1 Verify every scenario in `specs/text-execution/spec.md` maps to at least one Pest test
- [x] 17.2 Verify every scenario in `specs/prompt-blueprint/spec.md` maps to at least one Pest test
- [x] 17.3 Verify every scenario in `specs/tool-authorization/spec.md` maps to at least one Pest test
- [x] 17.4 Walk through `UPGRADE.md` against the actual diff for accuracy
