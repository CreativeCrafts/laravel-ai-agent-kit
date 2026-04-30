# Upgrade Guide

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

### Attachments scope

Attachments attached via `PromptBlueprint::withAttachment()` / `withAttachments()` are scoped to the **current call only**. The conversation memory bridge does not persist attachments across turns in this phase. Continuing a stored conversation will not replay prior attachments.

### AudioToTextToEvaluation transcription path

When `audio_reference` is **raw base64** or a `data:*;base64,...` data URI, the transcription stage calls `TranscriptionRuntime` (default: Laravel AI via `SdkTranscriptionRuntime`) using the orchestration provider profile name as the Laravel AI provider key. **Opaque references** (for example `s3://...`) are unchanged: the kit still builds the registered transcription prompt and runs `AiRuntime::execute()`.

### Modality runtimes

The package registers `TranscriptionRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, and `RerankingRuntime` in the container. Defaults use `ai-agent-kit.modalities.*.default_driver` = `sdk`. Set `default_driver` to a class-string that implements the corresponding contract to swap implementations.

### Documented limitations

- Whether `temperature` / `maxTokens` / `maxSteps` are honoured by the provider driver is driver-specific. Mainline providers (OpenAI, Anthropic, …) honour them; edge drivers may vary.
- Class-string schemas are resolved through the Laravel container. The resolved instance must implement `Laravel\Ai\Contracts\HasStructuredOutput` or a `SchemaResolutionException` is raised before the SDK is invoked.