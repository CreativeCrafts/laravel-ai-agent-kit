# PLAN.md — Laravel AI-Agent Package Implementation Roadmap (Laravel AI SDK Aligned)

This roadmap is designed to convert cleanly into GitHub milestones + issues.

- **Milestones** = Phases (`P0…P9`)
- **Epics** = Deliverables (`P0-E1…`)
- **Issues** = Implementable tasks (`P0-I1…`)

## Architectural Stance

This package is an **opinionated Laravel AI application layer built on Laravel AI SDK**.

- **Laravel AI SDK** is the runtime substrate for model/provider execution, tools, vector-capable flows, failover-capable agent execution, and SDK-native events.
- **This package** owns higher-level workflow composition, prompt governance, tool governance, memory policy, resilience policy, security/compliance defaults, redacted telemetry, scaffolding, and
  documentation patterns.
- **Public contracts remain package-owned.**
- **Vendor SDK types must not leak into `src/Contracts/**` or README-documented public API surfaces.**

## Execution Order Override (Authoritative for Implementation Sequencing)

The milestone structure remains the authoritative **thematic roadmap**, but the practical **implementation sequence** is optimized to reduce churn. In particular, two early P0 tasks are intentionally
deferred until the core package surface is stable:

- **P0-I11 — SDK-backed fake/testing strategy** is deferred until the runtime, pipeline, memory, tools, resilience, and package-owned fake seams are stable enough to document coherently.
- **P0-I12 — Docs: package positioning, install, architecture, migration notes** is deferred until the public package surface and contributor guidance have largely settled, to avoid repeated rewrites.

### Revised Execution Waves

1. **SDK substrate alignment**
		- P0-I1 → P0-I10
2. **Core workflow and runtime policy**
		- P1-I1 → P1-I10
		- P2-I1 → P2-I9
		- P3-I1 → P3-I5
		- P4-I1 → P4-I5
		- P5-I1 → P5-I3
		- P6-I1 → P6-I4
3. **Scaffolding and advanced project ergonomics**
		- P3-I6 → P3-I8
		- P8-I1 → P8-I3
4. **Deferred testing-strategy and release-hardening docs**
		- P0-I11
		- P7-I1 → P7-I3
		- P1-I11
		- P9-I1 → P9-I3
		- P0-I12
5. **Optional / spec-driven follow-ons**
		- P5-I4 → P5-I6
		- P7-I4

### Interpretation Rule

When choosing the “next logical issue,” use this execution-wave order first. Milestone grouping still communicates architecture and scope, but the execution sequence above is the authoritative order
for implementation unless the user explicitly overrides it.

## Global Labels (Recommended)

**Type**

- `type:epic`, `type:feature`, `type:task`, `type:docs`, `type:chore`

**Area (DDD-Lite modules)**

- `area:core`, `area:runtime`, `area:prompts`, `area:tools`, `area:memory`,
  `area:resilience`, `area:vector`, `area:security`, `area:observability`,
  `area:scaffolding`, `area:docs`

**Priority**

- `priority:P0`, `priority:P1`, `priority:P2`

**Risk**

- `risk:security`, `risk:perf`, `risk:cost`, `risk:breaking-change`

**Status**

- `status:blocked`, `status:needs-spec`, `status:ready`

## Global Definition of Done (DoD) — apply to every Issue

- [ ] Scope matches plan (no hidden additions)
- [ ] Public API/Contracts updated (if applicable) with additive changes
- [ ] Config keys documented + validated (fail-fast)
- [ ] Typed exceptions for failure paths
- [ ] Deterministic tests (no network, controlled time)
- [ ] Telemetry/events emitted (redacted by default) when applicable
- [ ] Docs updated (README or docs module)
- [ ] No vendor SDK types leak into public contracts

---

# Milestone P0 — Laravel AI SDK Alignment & Runtime Boundary (Priority: P0)

**Goal:** Make Laravel AI SDK the package’s execution substrate while preserving package-owned public contracts and fluent APIs.  
**Depends on:** —  
**Exit Criteria:** `laravel/ai` declared, internal runtime bridge exists, package runtime delegates to SDK, docs aligned, deterministic SDK-backed tests exist.

## Epic P0-E1 — Dependency + Runtime Boundary

- **Labels:** `type:epic`, `area:runtime`, `priority:P0`, `risk:breaking-change`
- **In-scope:** composer dependency, runtime bridge contracts/DTOs, internal SDK anti-corruption layer
- **Out-of-scope:** full blueprint migration (P0-E2), event normalization (P0-E4)
- **Issues:**
		- **P0-I1** Add `laravel/ai` as an explicit dependency + install/config wiring  
		  Labels: `type:feature`, `area:runtime`, `priority:P0`, `risk:breaking-change`, `status:ready`
		- **P0-I2** Define package-owned runtime contracts + internal SDK runtime bridge  
		  Labels: `type:feature`, `area:runtime`, `priority:P0`, `status:ready`
		- **P0-I3** Tests: runtime bridge wiring + no-vendor-leak guardrails  
		  Labels: `type:task`, `area:runtime`, `priority:P0`, `status:ready`

## Epic P0-E2 — Prompt / Tool / Agent Mapping

- **Labels:** `type:epic`, `area:runtime`, `priority:P0`
- **In-scope:** prompt rendering into SDK instructions/messages, tool materialization into SDK tools/provider tools, blueprint compilation path
- **Out-of-scope:** memory bridge (P0-E3)
- **Issues:**
		- **P0-I4** Prompt repository → SDK instruction/message mapper  
		  Labels: `type:feature`, `area:prompts`, `area:runtime`, `priority:P0`, `status:ready`
		- **P0-I5** Tool registry → SDK tool/provider-tool mapper  
		  Labels: `type:feature`, `area:tools`, `area:runtime`, `priority:P0`, `risk:security`, `status:ready`
		- **P0-I6** Blueprint compilation layer for SDK-backed execution  
		  Labels: `type:feature`, `area:core`, `area:runtime`, `priority:P0`, `status:ready`

## Epic P0-E3 — Memory / Vector Context Bridges

- **Labels:** `type:epic`, `area:runtime`, `priority:P0`, `risk:security`
- **In-scope:** package memory/context projection into SDK runtime, SDK-backed vector adapter strategy
- **Out-of-scope:** optional additional vector adapters
- **Issues:**
		- **P0-I7** Package memory → SDK conversation context bridge  
		  Labels: `type:feature`, `area:memory`, `area:runtime`, `priority:P0`, `risk:security`, `status:ready`
		- **P0-I8** SDK-backed vector adapter strategy + boundary rules  
		  Labels: `type:feature`, `area:vector`, `area:runtime`, `priority:P0`, `status:ready`
		- **P0-I9** Tests: memory/vector bridge behavior  
		  Labels: `type:task`, `area:runtime`, `area:memory`, `area:vector`, `priority:P0`, `status:ready`

## Epic P0-E4 — Event Normalization + Docs Realignment

- **Labels:** `type:epic`, `area:observability`, `priority:P0`
- **In-scope:** SDK event normalization, redacted package telemetry, docs realignment, architecture docs
- **Issues:**
		- **P0-I10** Normalize/enrich SDK events into package telemetry  
		  Labels: `type:feature`, `area:observability`, `area:runtime`, `priority:P0`, `status:ready`
		- **P0-I11** SDK-backed fake/testing strategy *(deferred until core package seams stabilize; see Execution Order Override above)*  
		  Labels: `type:task`, `area:observability`, `area:runtime`, `priority:P0`, `status:ready`
		- **P0-I12** Docs: package positioning, install, architecture, migration notes *(deferred until late release-hardening; see Execution Order Override above)*  
		  Labels: `type:docs`, `area:docs`, `priority:P0`, `status:ready`

---

# Milestone P1 — Core Workflow Infrastructure (Priority: P0)

**Goal:** Establish workflow composition, provider policy, queueing, and publishing on top of the SDK-backed runtime boundary.  
**Depends on:** P0-E1, P0-E2  
**Exit Criteria:** workflow runner + provider policy + queue execution + package install/publish + baseline tests.

## Epic P1-E1 — Provider Policy + Config Validation

- **Labels:** `type:epic`, `area:core`, `priority:P0`, `risk:security`
- **In-scope:** provider policy, provider profile selection, failover policy, config validation
- **Out-of-scope:** retry/backoff/circuit breaker (P4)
- **Issues:**
		- **P1-I1** Config schema + ConfigValidator (fail-fast boot validation)  
		  Labels: `type:feature`, `area:core`, `priority:P0`, `risk:security`, `status:ready`
		- **P1-I2** Provider profiles + selection policy over Laravel AI SDK  
		  Labels: `type:feature`, `area:core`, `area:runtime`, `priority:P0`, `status:ready`
		- **P1-I3** Failover policy + typed exceptions  
		  Labels: `type:feature`, `area:core`, `area:resilience`, `priority:P0`, `status:ready`
		- **P1-I4** Tests: config validation + provider policy + failover policy  
		  Labels: `type:task`, `area:core`, `priority:P0`, `status:ready`

## Epic P1-E2 — Pipeline Core (sync + queued)

- **Labels:** `type:epic`, `area:core`, `priority:P0`
- **In-scope:** `PipelineBuilder`, step interfaces/DTOs, sync workflow runner, queued workflow runner
- **Out-of-scope:** orchestration retries (P4)
- **Issues:**
		- **P1-I5** Define pipeline step contracts + `RunContext` DTO  
		  Labels: `type:feature`, `area:core`, `priority:P0`, `status:ready`
		- **P1-I6** Implement `PipelineBuilder` + workflow runner over SDK runtime  
		  Labels: `type:feature`, `area:core`, `area:runtime`, `priority:P0`, `status:ready`
		- **P1-I7** Implement queue job(s) for workflow execution + result handling  
		  Labels: `type:feature`, `area:core`, `priority:P0`, `status:ready`
		- **P1-I8** Tests: pipeline chaining + queued execution (SDK-backed fakes)  
		  Labels: `type:task`, `area:core`, `priority:P0`, `status:ready`

## Epic P1-E3 — Package Installation + Publishing

- **Labels:** `type:epic`, `area:core`, `priority:P0`
- **Issues:**
		- **P1-I9** Service provider bindings + publish tags  
		  Labels: `type:feature`, `area:core`, `priority:P0`, `status:ready`
		- **P1-I10** Tests: config publish + container bindings sanity  
		  Labels: `type:task`, `area:core`, `priority:P0`, `status:ready`
		- **P1-I11** Docs: install + quickstart  
		  Labels: `type:docs`, `area:docs`, `priority:P0`, `status:ready`

---

# Milestone P2 — Conversation Memory & State (Priority: P0)

**Goal:** Provide package-owned conversation persistence, retention, and summarization with SDK context bridge compatibility.  
**Depends on:** P1-E1, P1-E2, P0-E3  
**Exit Criteria:** DB driver + one ephemeral driver + retention + summarization hook + bridge tests.

## Epic P2-E1 — Memory Contracts + RunContext Integration

- **Issues:**
		- **P2-I1** Define memory contracts  
		  Labels: `type:feature`, `area:memory`, `priority:P0`, `status:ready`
		- **P2-I2** Integrate memory into workflow context  
		  Labels: `type:feature`, `area:memory`, `area:core`, `priority:P0`, `status:ready`
		- **P2-I3** Tests: conversation start/continue flow  
		  Labels: `type:task`, `area:memory`, `priority:P0`, `status:ready`

## Epic P2-E2 — Database Driver + Retention

- **Issues:**
		- **P2-I4** DB schema + migrations  
		  Labels: `type:feature`, `area:memory`, `priority:P0`, `status:ready`
		- **P2-I5** DB driver implementation + retention purge service  
		  Labels: `type:feature`, `area:memory`, `priority:P0`, `risk:security`, `status:ready`
		- **P2-I6** Tests: retention purge + delete semantics  
		  Labels: `type:task`, `area:memory`, `priority:P0`, `status:ready`

## Epic P2-E3 — Ephemeral Driver + Summarization Hook

- **Issues:**
		- **P2-I7** Redis or in-memory driver  
		  Labels: `type:feature`, `area:memory`, `priority:P0`, `status:ready`
		- **P2-I8** Summarization port + default summarizer stub  
		  Labels: `type:feature`, `area:memory`, `priority:P0`, `status:ready`
		- **P2-I9** Tests: summarization trigger thresholds + persistence  
		  Labels: `type:task`, `area:memory`, `priority:P0`, `status:ready`

---

# Milestone P3 — Prompt & Tool Governance (Priority: P1)

**Goal:** Keep prompt and tool governance package-owned while executing through the SDK runtime.  
**Depends on:** P0-E2, P1-E2  
**Exit Criteria:** prompt repository + tool registry + SDK mapping + tests.

## Epic P3-E1 — Prompt Repository

- **Issues:**
		- **P3-I1** Prompt storage format + `PromptRepository`  
		  Labels: `type:feature`, `area:prompts`, `priority:P1`, `status:ready`
		- **P3-I2** Tests: interpolation, missing variables, version selection  
		  Labels: `type:task`, `area:prompts`, `priority:P1`, `status:ready`

## Epic P3-E2 — Tool Registry

- **Issues:**
		- **P3-I3** Tool contracts + registry + schema validation  
		  Labels: `type:feature`, `area:tools`, `priority:P1`, `status:ready`
		- **P3-I4** Authorization hook + default-deny policy  
		  Labels: `type:feature`, `area:tools`, `priority:P1`, `risk:security`, `status:ready`
		- **P3-I5** Tests: invalid schema rejection + deny-by-default behavior  
		  Labels: `type:task`, `area:tools`, `priority:P1`, `status:ready`

## Epic P3-E3 — CLI Scaffolding

- **Issues:**
		- **P3-I6** `ai:make:tool` scaffold  
		  Labels: `type:feature`, `area:scaffolding`, `priority:P1`, `status:ready`
		- **P3-I7** `ai:make:prompt` scaffold  
		  Labels: `type:feature`, `area:scaffolding`, `area:prompts`, `priority:P1`, `status:ready`
		- **P3-I8** Tests: scaffolds generate correct namespaces and paths  
		  Labels: `type:task`, `area:scaffolding`, `priority:P1`, `status:ready`

---

# Milestone P4 — Resilience & Orchestration Enhancements (Priority: P1)

**Goal:** Add retries/backoff/circuit breaker, budgets, timeouts, and package lifecycle events around SDK-backed execution.  
**Depends on:** P1-E1, P1-E2  
**Exit Criteria:** retry policy + circuit breaker + package events + tests.

## Epic P4-E1 — Retry/Backoff/Circuit Breaker Policies

- **Issues:**
		- **P4-I1** Retry policy config + backoff DTOs  
		  Labels: `type:feature`, `area:resilience`, `priority:P1`, `status:ready`
		- **P4-I2** Circuit breaker (state tracking + thresholds)  
		  Labels: `type:feature`, `area:resilience`, `priority:P1`, `status:ready`
		- **P4-I3** Tests: transient vs persistent failure behavior + breaker open/close  
		  Labels: `type:task`, `area:resilience`, `priority:P1`, `status:ready`

## Epic P4-E2 — Pipeline Events + Failover Telemetry

- **Issues:**
		- **P4-I4** Emit package lifecycle events + failover telemetry  
		  Labels: `type:feature`, `area:observability`, `priority:P1`, `status:ready`
		- **P4-I5** Tests: event emission + redaction defaults  
		  Labels: `type:task`, `area:observability`, `priority:P1`, `status:ready`

---

# Milestone P5 — Vector & Retrieval (Priority: P1)

**Goal:** Expose a package-owned vector port with at least one implementation and a documented SDK-backed adapter strategy.  
**Depends on:** P0-E3, P3-E2  
**Exit Criteria:** `VectorStoreInterface` + one implementation + tests + adapter strategy docs.

**Boundary rule:** SDK-backed retrieval remains an internal adapter strategy. `VectorStoreInterface`, `VectorDocument`, `VectorSearchQuery`, `VectorSearchResult`, and typed vector exceptions stay
package-owned and authoritative, and SDK types must not leak through those public surfaces.

## Epic P5-E1 — Vector Port + Reference Adapter

- **Issues:**
		- **P5-I1** Define `VectorStoreInterface` + typed errors  
		  Labels: `type:feature`, `area:vector`, `priority:P1`, `status:ready`
		- **P5-I2** Implement one adapter  
		  Labels: `type:feature`, `area:vector`, `priority:P1`, `status:ready`
		- **P5-I3** Tests: vector store contract suite + adapter compliance  
		  Labels: `type:task`, `area:vector`, `priority:P1`, `status:ready`

## Epic P5-E2 — Optional Additional Adapters

- **Issues (optional):**
		- **P5-I4** Adapter: RedisVector  
		  Labels: `type:feature`, `area:vector`, `priority:P2`, `status:needs-spec`
		- **P5-I5** Adapter: Qdrant  
		  Labels: `type:feature`, `area:vector`, `priority:P2`, `status:needs-spec`
		- **P5-I6** Adapter: Pinecone  
		  Labels: `type:feature`, `area:vector`, `priority:P2`, `status:needs-spec`

---

# Milestone P6 — Compliance & Security Layer (Priority: P1)

**Goal:** Encrypt at rest, redact PII, implement retention purge hooks, and enforce safe defaults around SDK-backed workflows.  
**Depends on:** P2, P3  
**Exit Criteria:** encryption + redaction + purge jobs + tests.

## Epic P6-E1 — Encryption + Redaction + Retention

- **Issues:**
		- **P6-I1** Encryption service abstraction + default implementation  
		  Labels: `type:feature`, `area:security`, `priority:P1`, `risk:security`, `status:ready`
		- **P6-I2** Redactor service  
		  Labels: `type:feature`, `area:security`, `area:observability`, `priority:P1`, `risk:security`, `status:ready`
		- **P6-I3** Purge jobs for retention policies  
		  Labels: `type:feature`, `area:security`, `area:memory`, `priority:P1`, `risk:security`, `status:ready`
		- **P6-I4** Tests: encryption at rest, redaction correctness, purge behavior  
		  Labels: `type:task`, `area:security`, `priority:P1`, `status:ready`

---

# Milestone P7 — Testing Harness & Observability (Priority: P2)

**Goal:** Provide first-class fakes/assertions and optional dashboards while keeping telemetry redacted and SDK-aware.  
**Depends on:** P0, P1–P4  
**Exit Criteria:** package fakes + SDK-aware assertions + optional dashboard hooks.

## Epic P7-E1 — Fakes + Assertions

- **Issues:**
		- **P7-I1** Fake runtime + fake provider policy + fake tool runner + fake vector store + fake memory store  
		  Labels: `type:feature`, `area:observability`, `area:runtime`, `priority:P2`, `status:ready`
		- **P7-I2** Assertion helpers  
		  Labels: `type:feature`, `area:observability`, `priority:P2`, `status:ready`
		- **P7-I3** Tests: fakes behave like real flows from a package perspective  
		  Labels: `type:task`, `area:observability`, `priority:P2`, `status:ready`

## Epic P7-E2 — Optional Dashboards

- **Issues (optional):**
		- **P7-I4** Pulse widgets / Nightwatch integration  
		  Labels: `type:feature`, `area:observability`, `priority:P2`, `status:needs-spec`

---

# Milestone P8 — Context-Aware Scaffolding (Priority: P2)

**Goal:** Provide safe scaffolding that understands both package conventions and Laravel AI SDK alignment assumptions.  
**Depends on:** P3-E3  
**Exit Criteria:** `ProjectInspector` + safe generators + tests.

## Epic P8-E1 — ProjectInspector + Safe Generators

- **Issues:**
		- **P8-I1** `ProjectInspector`  
		  Labels: `type:feature`, `area:scaffolding`, `priority:P2`, `status:ready`
		- **P8-I2** `ai:make:agent` and `ai:make:pipeline`  
		  Labels: `type:feature`, `area:scaffolding`, `area:core`, `priority:P2`, `status:ready`
		- **P8-I3** Tests: detection correctness + generated code compiles  
		  Labels: `type:task`, `area:scaffolding`, `priority:P2`, `status:ready`

---

# Milestone P9 — Documentation & Patterns (Priority: Ongoing)

**Goal:** Keep docs and runnable examples aligned with the SDK-backed architecture.  
**Depends on:** all milestones as they ship  
**Exit Criteria:** docs updated per feature + examples validated in CI.

## Epic P9-E1 — Docs + Examples as Build Artifact

- **Issues:**
		- **P9-I1** README: install + quickstart + configuration reference  
		  Labels: `type:docs`, `area:docs`, `priority:P2`, `status:ready`
		- **P9-I2** Architecture docs: module map + contracts + SDK bridge  
		  Labels: `type:docs`, `area:docs`, `priority:P2`, `status:ready`
		- **P9-I3** Example app or `examples/` folder + CI validation  
		  Labels: `type:docs`, `area:docs`, `priority:P2`, `status:ready`