# ASSISTANT_REPLACEMENT_SURFACE.md

## Status

This document is the implementation artifact for:

- `P1Y-I1 Define assistant replacement surface + compatibility contract`

It is an internal planning SSOT under `plan/`, not a package marketing document and not a promise of one-to-one API parity.

Its purpose is to answer one concrete project question:

> What meaningful functionality must `laravel-ai-agent-kit` preserve or introduce so that users switching from `laravel-ai-assistant` do not lose important capabilities, while still staying inside
> agent-kit architecture, boundaries, and long-term package goals?

This document defines that answer in **capability terms**, not raw legacy API terms.

---

## Why this document exists

`laravel-ai-assistant` and `laravel-ai-agent-kit` are not the same type of package.

- `laravel-ai-assistant` is currently an **OpenAI-oriented unified client package** with a strong DX surface around one primary builder (`Ai::responses()`), streaming, audio, images, files,
  conversations, webhooks, and thin wrappers for advanced OpenAI endpoints.
- `laravel-ai-agent-kit` is currently an **SDK-backed package-owned workflow/orchestration layer** with provider profiles, failover, tools governance, conversation memory, telemetry, vector
  abstractions, and flagship blueprints such as `TextToStructuredEvaluation` and `AudioToTextToEvaluation`.

Because the packages have different architectural centers, replacement cannot be defined as “same API, different package.”

Instead, replacement must be defined as:

- preserving the meaningful **user capability surface**,
- implementing missing capabilities in **package-owned** terms,
- and avoiding architectural regression into provider-specific thin wrappers.

---

## Source of Truth Basis

This audit is grounded in the latest inspected SSOT from both repositories.

### `laravel-ai-agent-kit` SSOT used

- `plan/SYSTEM-PROMPT.md`
- `plan/execution_protocol.md`
- `plan/PLAN.md`
- `README.md`
- `MULTI_AGENT_ORCHESTRATION.md`
- `composer.json`
- `config/ai-agent-kit.php`
- `src/LaravelAiAgentKitServiceProvider.php`

### `laravel-ai-assistant` SSOT used

- `README.md`
- `composer.json`

This document intentionally uses current repository SSOT rather than memory or earlier assumptions.

---

## Replacement Principle

`laravel-ai-agent-kit` should be considered a credible replacement for `laravel-ai-assistant` when a switching user can preserve the important functionality they relied on **without having to fall
back to the old package**.

That does **not** require:

- one-to-one API parity,
- one-to-one facade parity,
- OpenAI-specific endpoint wrapper parity,
- or preservation of provider-native object models as package public contracts.

It **does** require:

- meaningful capability coverage,
- package-owned request/result semantics,
- package-owned orchestration and workflow behavior,
- package-owned memory/tool/security/telemetry policy,
- and sufficiently low migration loss that users do not feel they lost important features.

---

## Non-Goals for Replacement

The following are explicitly **not** the definition of success:

1. Cloning `Ai::responses()` exactly.
2. Reproducing every raw OpenAI endpoint as a thin wrapper.
3. Preserving provider-native assistants, threads, runs, messages, vector store objects, or realtime session payloads as stable package public contracts.
4. Treating architectural differences as regressions when the underlying user capability is preserved in package-owned form.
5. Expanding agent-kit into an OpenAI-only general endpoint client.

---

## Classification Legend

Each capability below is classified as one of:

- **Covered**  
  Current agent-kit SSOT already provides a meaningful package-owned replacement for the capability.

- **Partial**  
  Current agent-kit SSOT contains substantial primitives or adjacent package-owned behavior, but a switching user would still lose part of the practical capability unless additional work lands.

- **Missing**  
  Current agent-kit SSOT does not yet provide a meaningful replacement capability.

- **Intentionally Out of Scope**  
  The exact legacy feature should not be reproduced as-is because it conflicts with agent-kit architecture. If user value still matters, it must be replaced by a package-native equivalent rather than
  a raw legacy clone.

---

## Capability Matrix

| Legacy assistant capability surface                   | `laravel-ai-assistant` SSOT evidence                                                               |                                                                                                                     Current `laravel-ai-agent-kit` status | Classification                           | Switching-user impact        | Replacement interpretation                                                                                                                |
|-------------------------------------------------------|----------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------:|------------------------------------------|------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| General text generation / one-shot assistant response | `Ai::responses()->input()->message(...)->send()` in assistant README                               |         Agent-kit ships orchestrator, runtime, pipelines, and evaluation blueprints, but no shipped general-purpose package-owned text assistant workflow | Partial                                  | High                         | Users need a package-owned general text/task workflow, not just specialized evaluation blueprints                                         |
| Unified primary DX surface                            | Assistant README centers everything on `Ai::responses()`                                           | Agent-kit exposes multiple package-owned surfaces (`AgentOrchestrator`, blueprints, pipelines) but no unified migration-safe high-level assistant surface | Partial                                  | Medium                       | This is primarily an ergonomics gap, not necessarily a core capability gap                                                                |
| Structured output                                     | Assistant README advertises structured output                                                      |                                                                                   Agent-kit ships `TextToStructuredEvaluation` and structured result DTOs | Partial                                  | High                         | Capability exists, but cross-provider reliability and malformed-output hardening remain necessary                                         |
| Tool calling                                          | Assistant README shows chat sessions with tool definitions                                         |                                                   Agent-kit has tool registry, schema validation, authorizer, SDK tool materialization, and orchestration | Partial                                  | High                         | The governance substrate exists, but a switching user still lacks a first-class general tool-enabled assistant workflow                   |
| Streaming responses                                   | Assistant README includes `stream()` and normalized SSE events                                     |                                                                      Agent-kit current SSOT does not ship a documented general streaming workflow surface | Missing                                  | High for chat-oriented users | Streaming should be added only in package-owned workflow/runtime form                                                                     |
| Conversations / continuity                            | Assistant README shows `Ai::conversations()->create()` and `inConversation(...)`                   |                       Agent-kit ships package memory drivers, context manager, conversation IDs, persistence, retention, and workflow context integration | Partial                                  | High                         | Memory exists, but switching users still need a practical general-purpose continuation surface, not only lower-level memory primitives    |
| Audio transcription                                   | Assistant README supports raw transcription through `Ai::responses()->input()->audio(...)->send()` |                                                                                Agent-kit ships `AudioToTextToEvaluation`, including a transcription stage | Partial                                  | High                         | Transcription exists inside a staged blueprint, but there is no shipped transcription-only first-class workflow                           |
| Speech synthesis / TTS                                | Assistant README supports `action => speech`                                                       |                                                                                         No shipped TTS capability is documented in current agent-kit SSOT | Missing                                  | Medium to High               | If assistant users rely on speech output, agent-kit needs a package-owned TTS workflow or capability surface                              |
| Image generation / editing                            | Assistant README supports image generation and editing                                             |                                                                                         No shipped image workflow is documented in current agent-kit SSOT | Missing                                  | Medium to High               | If this is part of expected switching-user functionality, agent-kit needs package-owned image workflows rather than raw provider wrappers |
| Files upload / file content retrieval                 | Assistant README exposes files upload and content access                                           |                                                                           No package-owned file lifecycle surface is documented in current agent-kit SSOT | Missing                                  | Medium                       | Raw file endpoints need not be cloned, but users may still need package-owned document/context ingestion capability                       |
| Webhooks                                              | Assistant README documents webhook config and signing                                              |                                                                                                No webhook surface is documented in current agent-kit SSOT | Missing                                  | Medium                       | Decide whether webhook support belongs in agent-kit as a package-owned external integration surface                                       |
| Observability                                         | Assistant README advertises observability                                                          |                                                     Agent-kit ships redacted telemetry, lifecycle events, normalized SDK events, and orchestration traces | Covered                                  | High                         | Agent-kit already has stronger package-owned observability semantics                                                                      |
| Files + retrieval-assisted workflows                  | Assistant exposes files and vector stores as raw features                                          |                                                                    Agent-kit has vector store contract and in-memory adapter, plus orchestrated workflows | Partial                                  | Medium                       | The replacement target should focus on package-owned retrieval/document workflows, not raw OpenAI vector store parity                     |
| Advanced endpoint wrappers: Moderations               | Assistant README exposes thin wrapper                                                              |                                                                                                               No current package-owned moderation surface | Intentionally Out of Scope (raw wrapper) | Low to Medium                | If moderation is needed, add a provider-neutral package moderation capability rather than a raw OpenAI endpoint wrapper                   |
| Advanced endpoint wrappers: Batches                   | Assistant README exposes thin wrapper                                                              |                                                                                                          No current package-owned batch execution wrapper | Intentionally Out of Scope (raw wrapper) | Low                          | If batching matters, it should be addressed as package workflow execution semantics, not raw endpoint parity                              |
| Advanced endpoint wrappers: Realtime Sessions         | Assistant README exposes thin wrapper                                                              |                                                                                                         No current package-owned realtime session surface | Intentionally Out of Scope (raw wrapper) | Medium                       | If realtime becomes important, it should be designed as a package-native runtime capability, not cloned from OpenAI sessions              |
| Advanced endpoint wrappers: Assistants API            | Assistant README exposes thin wrapper                                                              |                                                       Agent-kit orchestration and agent model already occupy this conceptual space in package-owned terms | Intentionally Out of Scope (raw wrapper) | Medium                       | Raw OpenAI Assistants API parity is not a valid target; package-owned orchestrator/agent workflows are the replacement form               |
| Advanced endpoint wrappers: Vector Stores API         | Assistant README exposes thin wrapper                                                              |                                                                             Agent-kit already defines `VectorStoreInterface` and ships an initial adapter | Partial                                  | Medium                       | Package-owned vector abstractions are the right replacement target, not raw OpenAI vector store wrappers                                  |

---

## Replacement Target by Priority

The audited surface above translates into the following priority bands.

### Priority A — Must preserve for switching users

These are the most important capabilities users should not lose when switching:

1. **General text/task execution**
2. **Structured output**
3. **Tool-enabled execution**
4. **Conversation continuity**
5. **Audio transcription / audio-to-result workflows**
6. **Observability**
7. **Retrieval-aware / document-aware package workflows where user value depends on it**

### Priority B — Strongly desirable for credible replacement

These are meaningful capabilities that may not block all switching users, but their absence will be felt:

1. **Streaming**
2. **Speech synthesis / TTS**
3. **Image generation / editing**
4. **Transcription-only first-class workflow**
5. **Package-owned document/file ingestion or context workflow**
6. **Webhook support if external event callbacks are part of expected integration stories**

### Priority C — Not valid targets as raw parity work

These should not be recreated as thin raw legacy wrappers:

1. **Raw OpenAI Assistants API wrapper parity**
2. **Raw Batches endpoint wrapper parity**
3. **Raw Realtime Sessions endpoint wrapper parity**
4. **Raw Moderations endpoint wrapper parity**
5. **Raw Vector Stores endpoint wrapper parity**

If user value matters in these areas, replacement must happen through **package-native capabilities**, not by turning agent-kit into another OpenAI thin client.

---

## Coverage Assessment Summary

### Already strong in current agent-kit SSOT

Agent-kit already has real strength in the following areas:

- package-owned orchestration
- provider profiles and failover
- package-owned memory and retention
- tool governance and schema validation
- package-owned telemetry and traces
- vector abstraction boundary
- staged blueprint architecture
- structured package-owned result DTOs

These are genuine replacement advantages, not just alternative implementations.

### Current practical gaps for switching users

The main gaps that would still make users feel feature loss are:

1. **No shipped general-purpose text assistant workflow**
2. **No shipped general-purpose tool-enabled assistant workflow**
3. **No shipped streaming workflow surface**
4. **No shipped transcription-only workflow**
5. **No shipped TTS workflow**
6. **No shipped image workflow**
7. **No shipped package-owned file/context ingestion surface**
8. **No decision yet on webhook support**
9. **Structured-output reliability still needs parity hardening across providers**
10. **Current examples and package narratives are stronger in evaluation/orchestration than in general assistant-class workflows**

---

## What “Replacement” Means After This Audit

From this point forward, the package should treat “replacement for `laravel-ai-assistant`” as meaning:

- users can preserve meaningful assistant-era functionality,
- through package-owned workflows and capabilities,
- with package-owned security, memory, telemetry, and provider semantics,
- without requiring raw OpenAI thin-wrapper parity.

This is the operative contract.

Any future issue, docs claim, or release-readiness decision in `P1Y` should be judged against this definition.

---

## True Blockers Identified by the Audit

The following are the most important blockers for switching users today.

### Blocker 1 — No package-owned general assistant workflow

Current SSOT has specialized evaluation workflows and a general orchestrator, but not a shipped general-purpose “assistant-like” package workflow for common text tasks.

**Implication:**  
Switching users can preserve some specialized flows, but not yet the broad everyday assistant use case in a simple package-owned form.

### Blocker 2 — No first-class general tool-enabled workflow surface

Tool governance exists, but a switching user does not yet get an obvious package-owned assistant-class surface for “ask a question, let tools participate, return one result.”

**Implication:**  
Important assistant-era workflows remain technically possible only through custom orchestration, not a packaged migration-safe surface.

### Blocker 3 — No streaming package surface

For users relying on streamed responses, there is no shipped package-native replacement yet.

**Implication:**  
This is a meaningful functionality loss for chat- or UX-heavy integrations.

### Blocker 4 — No transcription-only first-class workflow

The package ships `AudioToTextToEvaluation`, which is valuable, but users who just need transcription still lack a first-class package-owned transcription workflow.

### Blocker 5 — No TTS or image workflow yet

If switching users depend on these capabilities, they currently lose them in agent-kit.

### Blocker 6 — Structured output needs parity hardening

The package has the right output shape direction, but cross-provider reliability must be hardened before structured output can be treated as fully preserved functionality.

---

## How This Should Drive the Remaining P1Y Issues

This audit changes how the rest of the milestone should be interpreted.

### P1Y-I2

`P1Y-I2 Implement assistant-style facade / adapter over orchestrator` must be treated as **conditional ergonomics work**, not the whole replacement story.

If a facade lands, it should close real migration friction around:

- general text/task execution,
- tool-enabled flows,
- and conversation continuation,

not attempt to clone the legacy API.

### P1Y-I3

`P1Y-I3 Structured output normalization + repair pipeline` is confirmed as a **real blocker issue**, not an optional refinement.

### P1Y-I4 / P1Y-I5 / P1Y-I6 / P1Y-I7

These remain valid and high-value because they prove:

- provider capability coverage,
- cross-provider text parity,
- mixed-provider staged audio parity,
- and normalized failure/telemetry behavior.

### P1Y-I8

Examples and presets should focus on **real assistant-class workflows expressed in package terms**, not only provider demos.

### P1Y-I9

Migration docs should come late and must describe **proven capability coverage**, not planned parity.

### P1Y-I10

Release readiness must be measured against this audited capability target, not vague “assistant replacement” language.

---

## Additional Backlog Expansion Needed

The current P1Y stack is improved, but this audit shows that the following additional implementation work is likely required if the package truly wants to avoid switching-user feature loss.

These should be created as follow-on issues or folded into existing ones deliberately:

1. **General text/task workflow issue**  
   Ship a package-owned first-class text assistant workflow that is not limited to structured evaluation.

2. **General tool-enabled workflow issue**  
   Ship a package-owned assistant-class workflow where registered tools can participate in a migration-safe way.

3. **Streaming issue**  
   Add a package-native streaming execution surface.

4. **Transcription-only workflow issue**  
   Ship a first-class transcription result workflow separate from evaluation.

5. **TTS issue**  
   Add package-owned speech synthesis capability if this remains in-scope for switching users.

6. **Image workflow issue**  
   Add package-owned image generation/editing workflows if this remains in-scope.

7. **Document/context ingestion issue**  
   Decide whether and how file/document ingestion should become a package-owned capability.

8. **Webhook decision issue**  
   Explicitly decide whether webhook support belongs in agent-kit and, if yes, in what package-owned form.

This document does not create those issues automatically, but it identifies them as the missing-delta backlog implied by the audit.

---

## Decision Rules Going Forward

From this point forward, apply these rules to any “assistant replacement” claim or implementation:

1. Do not ask whether the package matches the old API exactly.
2. Ask whether the package preserves the **meaningful user capability**.
3. Prefer package-owned workflows and DTOs over raw provider endpoint wrappers.
4. Do not treat raw OpenAI thin-wrapper parity as the success metric.
5. Do not declare replacement readiness until the high-impact missing capabilities are either:
		- implemented, or
		- explicitly declared out of scope with acceptable switching trade-offs.

---

## Final Outcome of P1Y-I1

P1Y-I1 is complete when this document is accepted as the internal capability-audit SSOT for the milestone.

The key outcome is:

- replacement is now defined in terms of **functional capability coverage**,
- not in terms of **one-to-one legacy API matching**,
- and the real blockers for switching users are now explicit.

This document should be referenced by all remaining `P1Y` work until the milestone is complete.