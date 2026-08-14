# Upgrade guide

## Shared circuit breaker state

The circuit breaker remains `in_memory` by default for 1.x compatibility, so its state is process-local. Multi-worker deployments can set `resilience.circuit_breaker.driver=cache` and select a shared `cache_store`. The selected cache store must support Laravel atomic locks; Agent Kit fails during resolution when it cannot guarantee atomic state transitions.

## Media security and privacy

`safeMetadata()` no longer emits upload filenames or path/storage basenames by default. Set `media_input.include_diagnostic_names=true` only when those potentially identifying values are required for diagnostics.

The new `media_input.require_https` and `media_input.host_match` settings default to `false` and `exact_and_subdomains` to preserve 1.x behavior. Applications accepting user-influenced URLs should prefer HTTPS with `exact_only` matching and an explicit allowlist.

Stored transcription MIME values are now applied through Laravel AI's supported stored-audio MIME API. Code that relied on disk MIME detection taking precedence over an explicitly supplied MIME should stop passing the optional MIME argument.

## Cost budget preflight

Cost ceilings are now evaluated before provider dispatch through `CostEstimator`. Request `cost_usd` and `estimated_cost_usd` values remain supported as declared estimates but are not reported as actual provider charges. Strict mode remains fail-closed when an estimate is unavailable; set `AI_AGENT_KIT_COST_ESTIMATION_MODE=advisory` to permit dispatch with `cost_unknown=true` telemetry.

## Conversation revision migration

Publish and run `add_revision_to_ai_agent_conversations_table` before deploying this version with the database memory driver. Persistent stores now reject stale full-aggregate saves with `ConversationWriteConflictException`; reload the aggregate and make an application-level decision instead of silently merging. Redis deployments require permission to execute the package's atomic Lua compare-and-set script. Persistence corruption now consistently surfaces as `ConversationStoreException`.

## Semantic pipeline retry behavior

Pipeline retries no longer retry every throwable. They now use the runtime failure classifier, so deterministic invalid input, configuration, authorization, unsupported capability, conversation, and budget failures fail immediately. Replace application-specific transient exceptions with a classified exception or custom `FailureClassifier` behavior if they are intentionally retryable. Queue retry properties remain unset unless explicitly supplied through `QueueDispatchOptions`.

## Capability-aware failover

Fallback profiles are now filtered by execution requirements before dispatch. Ordinary prompts require `text_generation`; schema requests also require `structured_output`. Custom integrations can append package-owned identifiers through `ExecutionRequest::$requiredCapabilities`. Provider profiles used as fallbacks must declare those capabilities. Existing custom implementations of `FailoverProviderSelector` remain source compatible; implement `CapabilityAwareFailoverProviderSelector` to participate in requirement-aware selection.

## Fallback model isolation

Explicit request models now apply only to the initial Agent Kit provider profile. Fallback profiles use their configured model or Laravel AI provider default. If existing profiles intentionally share a compatible model identifier, set `AI_AGENT_KIT_FAILOVER_MODEL_POLICY=preserve_when_same_sdk_provider`. The temporary `preserve_always_legacy` mode restores the previous behavior across all providers while migrating.

## 1.1.1 to the next release

### File prompt manifests now enforce their generated contract

The file prompt repository now honors `current_version`. If a manifest sets `current_version` below its highest registered semantic version, an unversioned `get()` or `render()` call selects the declared current version. Calls that pass an explicit version are unchanged. Manifests that omit `current_version` continue selecting the highest registered version.

Per-version `variables` declarations are now authoritative when present. The declared list must contain valid, unique names and exactly match the template's dynamic placeholders. An explicit empty list means the prompt accepts no variables. Manifests that omit `variables` continue inferring required variables for backward compatibility.

Before upgrading, validate application prompt roots:

~~~bash
php artisan ai:prompts:lint
~~~

Resolve any reported undeclared placeholders, unused or duplicate declarations, invalid `current_version` values, or missing template files. If a legacy manifest cannot be made explicit immediately, omit its `variables` key to retain inference; do not use an empty list unless the template intentionally has no dynamic placeholders.

### Literal double-curly syntax

Use `\{{name}}` in a prompt template to render `{{name}}` literally without declaring or supplying `name`. Two backslashes before a placeholder render one literal backslash followed by the substituted value. Malformed or unclosed placeholder syntax remains literal, and inserted variable values are not recursively interpolated.

### Prompt scaffolding preserves version history

`ai:make:prompt` now adds versions to an existing valid manifest instead of refusing whenever `metadata.php` exists. New versions do not change `current_version` unless `--activate` is supplied. For legacy manifests without `current_version`, the command pins the previously effective highest version before adding a newer version.

The meaning of `--force` is narrowed to replacing only the requested version definition and template. It no longer authorizes replacement of the complete manifest or deletion of unrelated prompt history. Existing invalid metadata must be repaired rather than overwritten with `--force`.

### Runtime failures now use semantic failover decisions

The runtime no longer treats every throwable as a provider-health failure. Only classified failover-safe failures consume another provider profile, and only classified provider-health failures increment circuit breakers. Invalid requests, unsupported capabilities, local configuration errors, authorization denials, budget failures, conversation failures, and unknown throwables fail closed.

Applications that deliberately relied on broad failover for custom untyped exceptions can temporarily restore the old behavior while migrating those exceptions:

~~~dotenv
AI_AGENT_KIT_UNKNOWN_FAILURE_MODE=legacy_failover
~~~

The default is `strict`. Prefer throwing supported Laravel AI transport/failover exceptions or preserving them as `$previous`; the classifier walks previous-exception chains. The legacy mode should be temporary because it can consume unrelated providers for deterministic local defects.

## 1.1.0 to 1.1.1

### Unsupported provider profile option keys now fail validation

When `validation.enabled` is true (the default), `providers.<profile>.options` accepts only `model` and `provider_options`. Sibling keys such as `reasoning_effort` or `temperature` that previously passed validation were ignored at runtime.

Move provider-native settings under `options.provider_options`:

~~~php
// Invalid
'options' => [
    'model' => 'gpt-example',
    'reasoning_effort' => 'medium',
],

// Correct
'options' => [
    'model' => 'gpt-example',
    'provider_options' => [
        'reasoning' => [
            'effort' => 'medium',
        ],
    ],
],
~~~

Typed generation controls such as `temperature` belong on `GenerationOptions`, not on the provider profile. Applications that set `validation.enabled` to `false` keep the previous permissive registry behavior.

### Optional audio-image evaluation input templates

`AudioImageStructuredEvaluationRequest` now accepts optional `evaluationInputTemplate` as the last constructor argument. Existing requests with no template still render as `<evaluationPrompt>\n\nTranscript:\n<transcript>`.

Queued jobs that already contain a serialized `AudioImageStructuredEvaluationRequest` remain compatible: missing `evaluationInputTemplate` is restored as `null` and uses the default prompt composition.

## 1.0.x to 1.1.0

Agent Kit 1.1.0 requires `laravel/ai ^0.9` and makes the Laravel AI bridge semantically transparent. Most applications keep working. Review the items below if you configured provider profiles, generation options, or relied on the previous default system instruction.

### Laravel AI ^0.9

Update the application constraint if it pinned an older SDK:

~~~bash
composer update creativecrafts/laravel-ai-agent-kit laravel/ai
~~~

### Provider profile names are not Laravel AI provider names

Agent Kit now keeps three identities separate:

- provider profile (Agent Kit policy identity)
- SDK provider (named Laravel AI instance)
- driver

A profile such as `openai-image-audio-scorer` with `driver` `openai` is sent to Laravel AI as provider `openai`, unless you set `sdk_provider`.

If you previously created `config/ai.php` providers whose names matched Agent Kit profile names as a workaround, either:

- set `sdk_provider` to that Laravel AI instance name, or
- remove the alias and point `sdk_provider` (or `driver`) at the real Laravel AI provider instance.

### Empty instructions no longer invent a persona

Requests that supply no instructions no longer receive:

```text
You are the Laravel AI Agent Kit runtime bridge.
```

To restore a package-level default, set `runtime.default_instructions` in `config/ai-agent-kit.php`.

### Typed generation options use Laravel AI's typed channel

`GenerationOptions` `temperature`, `maxTokens`, and `maxSteps` are no longer placed in `providerOptions()`. Laravel AI translates them per provider. Put provider-native keys such as OpenAI `reasoning` in `providerOptions` or profile `options.provider_options`.

### Audio-image evaluation capabilities

`AudioImageStructuredEvaluation` now requires the evaluation profile to declare `text_generation` and `structured_output`, plus `image_input` or `vision`. Add `text_generation` to existing evaluation profiles that omitted it.

### Strict structured output

`ExecutionRequest::$strictStructuredOutput` defaults to `false`. Set it to `true` on `ExecutionRequest`, `PromptBlueprint`, or `AudioImageStructuredEvaluationRequest` when you need Laravel AI strict schema output.

### Disabled provider profiles

A registered profile with `enabled => false` now raises `ProviderDisabledException` even when selected explicitly. Previously, explicit lookup could still invoke that profile.
