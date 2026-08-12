# Upgrade guide

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
