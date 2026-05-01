# Upgrade Guide

## Documentation rollout (2026-05-01)

Phase 7 finalizes consumer-facing documentation for the `close-agent-kit-gaps` program. No new runtime APIs: use **`UPGRADE.md`** for migration steps per phase (1–6) and **`CHANGELOG.md`** under *Rollout* for the recommended adoption order. **`README.md`** is the canonical index for config keys (`runtime`, `modalities`, `memory.laravel_ai_legacy`, `memory.attachments_replay`), contracts (`AiRuntime`, `StreamingAiRuntime`, modality interfaces), and vector defaults (`in_memory` only unless you bind a custom `VectorStoreInterface`).

## Evolving the text-execution surface (Phase 1+2)

This release reshapes the request/result value objects in a single breaking-change window so consumers migrate once. New capabilities: typed `GenerationOptions`, structured-output schemas, multimodal attachments, and SDK-native provider tools.

### `ExecutionRequest` constructor

`CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest` gained four optional named parameters: `generationOptions`, `schema`, `attachments`, `providerToolNames`. Positional-argument callers must migrate to named arguments. The kit and its tests already use named arguments throughout.

Before:

```php
new ExecutionRequest(
    'run-1',
    'Summarize this text.',
    ['You are concise.'],
    'openai',
    'gpt-4o-mini',
);
```

After:

```php
new ExecutionRequest(
    runId: 'run-1',
    prompt: 'Summarize this text.',
    instructions: ['You are concise.'],
    provider: 'openai',
    model: 'gpt-4o-mini',
    generationOptions: new GenerationOptions(temperature: 0.2, maxTokens: 512),
    schema: \App\Schemas\SummarySchema::class,
    attachments: [$image],
    providerToolNames: ['web.search'],
);
```

If you have positional callsites in your application, sed-style replacement: extract the call, prefix every argument with its name (`runId: …`, `prompt: …`).

### `ExecutionResult` structured output accessor

`ExecutionResult` gained a `structuredOutput: array<string, mixed>|null` field. Existing consumers reading `->output` continue to work — `output: string` is still populated for both structured and unstructured calls.

```php
$result = $runtime->execute($request);

$plainText = $result->output;            // string – always populated
$payload = $result->structuredOutput;    // array<string, mixed>|null – populated when a schema drove the call
```

No migration required for unstructured consumers.

### `ToolAuthorizer` contract split

`CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer` now exposes two methods, one per tool family:

- `authorizeCustomTool(Tool $tool, array $input): bool` — local `Tool` implementations, content-aware policies.
- `authorizeProviderTool(string $providerToolName): bool` — SDK-native provider tools (`WebSearch`, `WebFetch`, `FileSearch`, …), name-based policies.

Custom authorizers must implement both methods. If you do not need to distinguish between the two families, extend the new `CreativeCrafts\LaravelAiAgentKit\Tools\AbstractToolAuthorizer` and override the single `authorize()` method:

```php
final class TenantToolAuthorizer extends AbstractToolAuthorizer
{
    protected function authorize(ToolKind $kind, string $name, ?Tool $tool, array $input): bool
    {
        return $this->tenantRegistry->canUse($kind->value, $name);
    }
}
```

The default `DenyAllToolAuthorizer` denies both families. Provider tools are denied by default even though they execute server-side at the model provider — they remain billable, rate-limited, and leak data to the provider.

### `AgentKitManager` constructor shape

`CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager` gained a fourth constructor dependency: `BlueprintRunner`. Container-resolved usage (`app(AgentKitManager::class)`) is unaffected — the service provider injects the new dependency. Only consumers that instantiate the manager positionally must update.

The manager exposes a new pass-through method for single-prompt execution:

```php
$result = AgentKit::run(
    LaravelAiAgentKit::prompt('package.followup-summary')
      ->withVariable('topic', 'refund window')
      ->withSchema(\App\Schemas\FollowUpSummary::class),
);
```

The `AgentKit` facade gains a matching `@method static ExecutionResult run(PromptBlueprint $blueprint)` annotation.

### TextToStructuredEvaluation structured-output path

`TextToStructuredEvaluation` now passes a stable `ObjectSchema` handle on the specialist `ExecutionRequest` (`CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredEvaluationJsonSchema::objectSchema()`). When the runtime returns a populated `ExecutionResult::$structuredOutput` that validates, the blueprint uses it as the primary path. Otherwise it falls back to parsing `ExecutionResult::$output` with the existing `StructuredEvaluationOutputNormalizer`.

`TextToStructuredEvaluationResult` gained two optional fields:

- `structuredEvaluationPath`: `structured_output` or `text_normalization`
- `structuredEvaluationRepaired`: `true` when the text fallback path repaired embedded or wrapped JSON (same meaning as the normalizer’s repaired status)

`toArray()` on the result includes `structured_evaluation_path` and `structured_evaluation_repaired`.

### Runtime middleware

Register optional middleware classes under `ai-agent-kit.runtime.middleware` (ordered list of class names). Each class must implement `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware` and is resolved from the container. When the list is non-empty, the package wraps `SdkAiRuntime` so **every** `AiRuntime::execute` call (direct, blueprint, orchestration) passes through the stack.

Implement `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\TerminatingRuntimeMiddleware` when you need a hook that runs **after** a successful execution, in reverse order relative to the `handle` chain.

### Runtime streaming

Inject `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime` (same concrete instance family as `AiRuntime`, including the middleware wrapper when configured). Call `executeStream(ExecutionRequest $request)` and consume the generator: zero or more `StreamChunk` (`text_delta`), then exactly one terminal `StreamComplete` or `StreamFailure`. Do not set `ExecutionRequest::$schema` for streaming.

Optional Laravel Echo integration: configure `ai-agent-kit.runtime.streaming.broadcast_channel` or set request metadata `streaming_broadcast_channel` to a public channel string. Broadcast event names are `runtime.stream.chunk`, `runtime.stream.completed`, and `runtime.stream.failed`; payloads omit prompt content and mirror the redacted Laravel events the package dispatches on the default event bus.

### Attachment persistence and replay (Phase 6)

When `store_conversation` is true, the memory bridge serializes each turn’s `ExecutionRequest::$attachments` into the user `ConversationMessage` metadata as JSON-safe rows (`PersistedLaravelAiFileSerializer`). Database stores persist these in `ai_agent_conversation_messages.attachments_ciphertext` (encrypted when `memory.database.encrypt_payloads` is true); Redis stores them in a sibling `attachments` array on each message payload.

**Replay is opt-in per request.** Set `ExecutionRequest` metadata `attachment_replay` to:

- `none` (default) — prior attachments are not merged into the current prompt.
- `merge` — replay allowed attachments from the **previous user turn**, then append the current request’s attachments (what the agent receives).
- `replay_only` — only replay allowed prior attachments (current request attachments are ignored for the prompt).

Enable policy evaluation with `ai-agent-kit.memory.attachments_replay.enabled`. The policy can deny types (default denies base64 and local paths), cap count per turn, enforce max age from the stored message timestamp, block provider file references, and deny remote URLs containing configured substrings (`authorization_denied`). When exclusions occur, the package dispatches `RuntimeAttachmentsReplayed` (types and reasons only; no URLs or payloads).

Publish or merge the updated migration stub if your app already created `ai_agent_conversation_messages` without `attachments_ciphertext` — add the nullable column in a follow-up migration.

### AudioToTextToEvaluation transcription path

When `audio_reference` is **raw base64** or a `data:*;base64,...` data URI, the transcription stage calls `TranscriptionRuntime` (default: Laravel AI via `SdkTranscriptionRuntime`) using the orchestration provider profile name as the Laravel AI provider key. **Opaque references** (for example `s3://...`) are unchanged: the kit still builds the registered transcription prompt and runs `AiRuntime::execute()`.

### Modality runtimes

The package registers `TranscriptionRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, `RerankingRuntime`, and `AudioGenerationRuntime` in the container. Defaults use `ai-agent-kit.modalities.*.default_driver` = `sdk`. Set `default_driver` to a class-string that implements the corresponding contract to swap implementations.

**Audio generation (TTS):** inject `AudioGenerationRuntime` and call `generate(AudioGenerationRequest)` with the text to synthesize. Optional: `voice`, `maleVoice`, `instructions`, `timeout`, `provider`, `model` (see `AudioGenerationRequest`). The SDK default audio provider is `config('ai.default_for_audio')` when `provider` is null on the request.

### Laravel AI legacy conversation read bridge (Phase 5)

If you previously stored conversations with Laravel AI’s default `DatabaseConversationStore` (`agent_conversations` / `agent_conversation_messages`), enable the optional read bridge so `ConversationStore::find()` can load them when no matching `ai_agent_*` row exists:

```php
// config/ai-agent-kit.php
'memory' => [
    'default_driver' => 'database',
    'laravel_ai_legacy' => [
        'enabled' => true,
        'connection' => null, // defaults to memory.database.connection
        'conversations_table' => 'agent_conversations',
        'messages_table' => 'agent_conversation_messages',
    ],
],
```

`save()` and `delete()` always use the package database tables; the bridge is **read-only** for legacy rows. Loaded legacy messages include a `metadata['laravel_ai']` payload (title, user id, tool JSON, usage, and so on) so nothing is dropped during migration.

### Documented limitations

- Whether `temperature` / `maxTokens` / `maxSteps` are honoured by the provider driver is driver-specific. Mainline providers (OpenAI, Anthropic, …) honour them; edge drivers may vary.
- Class-string schemas are resolved through the Laravel container. The resolved instance must implement `Laravel\Ai\Contracts\HasStructuredOutput` or a `SchemaResolutionException` is raised before the SDK is invoked.