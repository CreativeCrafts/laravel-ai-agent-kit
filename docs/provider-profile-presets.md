# Provider Profile Presets and Examples

## Status

This document is the implementation artifact for:

- `P1Y-I8 Ship Gemini/xAI and mixed-provider profile presets + examples`

It defines the shipped provider-profile presets and the example workflow patterns that are safe to recommend based on the current package architecture, audited capability matrix, and landed parity
work.

---

## Why this document exists

The package now has enough audited and tested provider-capability coverage to publish realistic starting points for switching users.

These presets and examples are meant to help users express real workflows in package terms:

- package-owned provider profiles
- capability-based selection
- blueprint-backed workflows
- orchestrator-backed workflows
- mixed-provider staged execution where the package has already proven it

This document is not a provider comparison sheet and it is not a legacy API emulation guide.

---

## What is shipped

The shipped preset catalog lives in:

- `examples/provider-profile-presets.php`

It currently includes:

- `gemini_structured_evaluation`
- `xai_orchestrator_text_generation`
- `xai_to_gemini_audio_review`

These are example presets. They are intended to be copied into an application’s published config and adapted there.

They are not automatically merged into package configuration.

---

## Core rule

Every preset in this document is grounded in package-owned capability semantics, not provider branding.

That means each example is constrained by the audited matrix:

- text-generation workflows require `text_generation`
- structured evaluation requires `text_generation` + `structured_output`
- audio transcription requires `audio_transcription`
- staged audio-to-text-to-evaluation requires:
		- `audio_transcription` for the transcription stage
		- `text_generation` + `structured_output` for the evaluation stage

If a preset cannot satisfy those package-owned requirements, it does not belong here.

---

## Guardrails

These presets intentionally do **not** claim:

- raw provider-native feature parity
- one-to-one `laravel-ai-assistant` API replacement
- unsupported structured-output behavior
- unsupported mixed-provider stage combinations
- architecture-bypassing shortcuts around blueprints, orchestration, memory, failover, or telemetry

They are designed to reinforce the package model, not bypass it.

---

## Preset catalog

### `gemini_structured_evaluation`

Use this preset when you want a Gemini-first setup for the package-owned `TextToStructuredEvaluation` blueprint.

It provides:

- `gemini-general` as the default general text-generation profile
- `gemini-structured` as the compatible structured-output profile

This preset is appropriate when your application wants Gemini-backed structured evaluation while keeping the final result DTO and workflow semantics package-owned.

### `xai_orchestrator_text_generation`

Use this preset when you want a minimal xAI profile for orchestrator-backed text-generation flows.

It provides:

- `xai-general` with `text_generation`

This preset is intentionally narrow. It is suitable for agent and orchestrator workflows that only require text generation.

It does **not** imply that xAI is being shipped here as a structured-output preset.

### `xai_to_gemini_audio_review`

Use this preset when you want a tested mixed-provider staged workflow.

It provides:

- `xai-transcription` for the audio transcription stage
- `gemini-structured` for the structured evaluation stage
- `xai-general` as the default text-generation profile for non-structured text flows in the same config set

This is the staged preset that most directly reflects the package’s current mixed-provider blueprint hardening work.

---

## How to apply a preset

Copy the selected preset into your published `config/ai-agent-kit.php`.

A typical pattern is:

1. load the preset catalog,
2. pick the preset you want,
3. merge its `providers`, `default_provider`, and `failover_order` into your application config.

Example:

~~~php
$presets = require base_path('vendor/creativecrafts/laravel-ai-agent-kit/examples/provider-profile-presets.php');

$preset = $presets['xai_to_gemini_audio_review'];

return [
    // ...
    'providers' => $preset['providers'],
    'default_provider' => $preset['default_provider'],
    'failover_order' => $preset['failover_order'],
    // ...
];
~~~

If your application already has additional provider profiles, merge selectively rather than replacing the full provider map blindly.

---

## Example workflows

### Gemini-first structured evaluation

Choose:

- `gemini_structured_evaluation`

Then run the package-owned blueprint normally:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;

$result = app(TextToStructuredEvaluation::class)->evaluate(
    new TextToStructuredEvaluationRequest(
        subject: 'support reply',
        text: 'We can refund the unused portion of your subscription within five business days.',
        enabledDimensions: ['clarity', 'accuracy', 'completeness'],
        promptVersion: '1.0.0',
    ),
);
~~~

What this means in package terms:

- one blueprint call
- one package-owned structured result
- one stable DTO contract
- provider selection resolved through compatible package-owned profiles

The preset changes which compatible profiles exist. It does not change the blueprint contract.

---

### Mixed-provider audio review

Choose:

- `xai_to_gemini_audio_review`

Then run the staged blueprint:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;

$result = app(AudioToTextToEvaluation::class)->evaluate(
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        audioMimeType: 'audio/wav',
        enabledDimensions: ['clarity', 'accuracy'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
    ),
);
~~~

What this means in package terms:

- the transcription stage resolves a profile with `audio_transcription`
- the evaluation stage resolves a profile with `text_generation` + `structured_output`
- the whole flow still returns one package-owned audio-evaluation result

This is the recommended way to express mixed-provider staged behavior in the package today.

---

### xAI orchestrator-backed text workflow

Choose:

- `xai_orchestrator_text_generation`

Then run an orchestrator-backed package workflow:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;

$result = app(AgentOrchestrator::class)->run(
    new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Draft a short, customer-safe response for a refund question.',
        input: ['subscription_id' => 'sub-123'],
    ),
);
~~~

What this means in package terms:

- you are using xAI for package-owned text-generation execution
- you are not claiming structured-output compatibility unless you explicitly configure and validate that separately
- orchestration, delegation, provider selection, and telemetry remain package-owned

---

## Current selection behavior

The current provider-profile selection model is capability-based and order-sensitive.

In practice, that means:

- the first enabled compatible profile in the configured provider registry order is selected
- preset ordering is therefore intentional
- reordering compatible profiles may change which provider profile becomes the preferred compatible profile for a workflow

This is current package behavior, not a marketing preference statement.

---

## Model options

The shipped presets do not hard-code provider-specific model names.

That is intentional.

Provider-specific model selection belongs in each profile’s `options` array inside the application that adopts the preset. This keeps the shipped examples:

- package-architecture aligned
- validation-safe
- less likely to drift as provider model inventories change

---

## What these presets are for

These presets are appropriate for:

- application bootstrap examples
- migration guidance for switching users
- architecture-aligned configuration starting points
- internal team baselines that want package-owned workflows rather than provider-native workflow design

They are not intended as the final word on every production setup.

---

## What these presets are not for

These presets are not:

- promises of full provider parity
- commitments to provider-native API cloning
- shortcuts around the package contracts
- replacements for validating your own application prompts, tools, memory policies, and operational constraints

---

## Verification basis

These presets are grounded in the following package artifacts:

- `plan/PROVIDER_CAPABILITY_MATRIX.md`
- `tests/TextToStructuredEvaluationBlueprintTest.php`
- `tests/AudioToTextToEvaluationBlueprintTest.php`
- `tests/ProviderProfilePresetExamplesTest.php`

That is the quality bar for future preset additions:

1. declare capabilities in package terms,
2. align with the audited matrix,
3. prove the behavior with deterministic tests,
4. document only what is actually implemented and hardened.

---

## Guidance for future presets

When adding a new preset later, keep these rules:

1. express the preset in package capability language
2. avoid provider marketing claims
3. do not imply one-to-one legacy API emulation
4. keep the example on a blueprint or orchestrator-backed workflow where appropriate
5. add deterministic regression coverage before documenting it
6. document only stable package-owned semantics

---

## Summary

The shipped Gemini, xAI, and mixed-provider presets are meant to be useful starting points for real package workflows.

They exist to help users say:

- “I want Gemini-backed structured evaluation”
- “I want xAI-backed text-generation orchestration”
- “I want xAI transcription with Gemini structured evaluation”

without leaving the package’s architecture, contracts, or tested behavior model.