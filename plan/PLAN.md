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

# Formal implementation issue stack

## 1. `P1X-I1 Define agent/orchestration contracts + DTOs`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:runtime`, `priority:P0`, `status:ready`

### Summary

Introduce package-owned multi-agent orchestration contracts and typed DTOs for agents, orchestration requests/results, execution context, delegation, handoff payloads, and execution trace metadata.

### Rationale

The package already has runtime, workflow, prompt, tool, and provider foundations. Multi-agent orchestration should be introduced as a package-owned capability with explicit contracts before any
flagship blueprint is implemented.

### Scope

* define `Agent` contract
* define `AgentDefinition`
* define `AgentExecutionContext`
* define `AgentExecutionResult`
* define `DelegationProposal`
* define `HandoffPayload`
* define `OrchestrationRequest`
* define `OrchestrationResult`
* define execution trace DTOs and correlation identifiers

### Out of scope

* orchestrator implementation
* provider selection logic
* concrete blueprint implementation
* dashboards/UI

### Dependencies

* existing runtime bridge
* existing pipeline/runtime/provider substrate in current SSOT

### Acceptance criteria

* package-owned contracts exist for agent orchestration
* DTOs are typed and package-owned
* no provider SDK types leak into public contracts
* delegation and handoff are represented as distinct concepts
* orchestration ID and per-execution ID model is explicit in DTOs

### Tests

* unit tests for DTO invariants
* architecture/static checks for no vendor leakage in orchestration contracts

### Risks

* risk of over-generalizing too early
* risk of making execution context too broad or too mutable

### Docs

* architecture docs must later explain new orchestration contracts and boundaries

---

## 2. `P1X-I2 Implement agent registry + Laravel container resolution`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `priority:P0`, `status:ready`

### Summary

Implement a package-owned agent registry that resolves first-class PHP agent classes through the Laravel container and exposes deterministic lookup by agent key.

### Rationale

Agents are the primary unit of specialization in the proposed model. The application must be able to register them explicitly and the package must resolve them predictably.

### Scope

* define `AgentRegistry` contract if needed across module boundaries
* support explicit application registration
* resolve agents through the container
* deterministic lookup by key
* validate duplicate or missing agent registrations

### Out of scope

* dynamic config-only agent model
* orchestration routing logic
* provider execution

### Dependencies

* `P1X-I1`

### Acceptance criteria

* first-class PHP agents can be registered explicitly
* agents resolve via container by stable key
* duplicate keys fail deterministically
* missing agents fail with typed package exceptions

### Tests

* integration tests for registration and resolution
* deterministic duplicate/missing-agent failure tests

### Risks

* risk of implicit auto-discovery creating hidden behavior
* risk of container resolution ambiguity

### Docs

* document recommended agent registration pattern for Laravel applications

---

## 3. `P1X-I3 Implement orchestrator core + execution tree + final orchestration result`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:runtime`, `priority:P0`, `status:ready`

### Summary

Implement the core orchestrator that executes agents, assigns orchestration and execution identifiers, records execution lineage, and returns a final orchestration result.

### Rationale

The orchestrator is the authoritative control plane. It must own execution identity, result assembly, and trace construction rather than allowing agents to manipulate orchestration state directly.

### Scope

* implement `AgentOrchestrator`
* assign one orchestration ID per workflow
* assign one execution ID per agent execution
* link parent/child execution lineage
* return one final orchestration result with compact summary
* support synchronous orchestration flow

### Out of scope

* provider selection/fallback
* delegation policy approval
* queued orchestration
* blueprint-specific logic

### Dependencies

* `P1X-I1`
* `P1X-I2`

### Acceptance criteria

* one orchestration ID spans the whole workflow
* one execution ID exists per agent execution
* parent-child lineage is tracked
* final result includes final status, final agent, final output, and orchestration summary
* orchestration does not expose provider-specific internals in public result DTOs

### Tests

* deterministic multi-step orchestration integration tests
* execution-tree and summary assertions using fakes

### Risks

* risk of leaking internal execution details into public API
* risk of coupling orchestration state too tightly to one execution style

### Docs

* architecture docs must describe orchestration ID vs execution ID semantics

---

## 4. `P1X-I4 Implement delegation/handoff policy engine`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:security`, `priority:P0`, `risk:security`, `status:ready`

### Summary

Implement a policy engine that evaluates agent-proposed delegation and handoff actions and authoritatively approves, rejects, or rewrites them.

### Rationale

Agents should be able to propose routing, but the package must remain governed. The orchestrator must remain the final authority for delegation and ownership transfer.

### Scope

* support policy modes:

  	* `static_only`
  	* `dynamic_with_allowlist`
  	* `dynamic_full_registry`
* validate target agent permissions
* support `delegate_and_resume`
* support `transfer_control`
* enforce max delegation depth and step limits
* support typed rejection reasons

### Out of scope

* human approval UI
* arbitrary autonomous routing without policy
* full RBAC system

### Dependencies

* `P1X-I3`

### Acceptance criteria

* agents can propose delegation/handoff
* orchestrator approves/rejects according to policy
* delegation and handoff remain distinct in behavior and trace
* default mode is safe and deterministic
* invalid proposals fail with typed package exceptions or typed orchestration failure states

### Tests

* deterministic policy approval/rejection tests
* max depth and invalid target tests
* delegation vs ownership-transfer behavior tests

### Risks

* risk of ambiguous routing semantics
* risk of unsafe open routing if defaults are too permissive

### Docs

* document delegation modes and safe default behavior

---

## 5. `P1X-I5 Implement provider profile assignment + capability compatibility for agents`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:runtime`, `area:core`, `priority:P0`, `status:ready`

### Summary

Add agent-level provider profile assignment, fallback candidates, and capability validation so agents can run on different providers without leaking provider specifics into workflow logic.

### Rationale

This package supports multiple providers, but orchestration must remain provider-neutral. Agents should declare a primary provider profile plus explicit fallback candidates, while the orchestrator
performs final profile selection.

### Scope

* define provider-profile selection rules for agents
* define capability declaration model for agents
* validate profile capability compatibility before execution
* support primary profile plus fallback candidates
* emit typed failures for unsupported capability/profile combinations

### Out of scope

* dynamic arbitrary provider choice outside agent constraints
* provider-specific endpoint details in public contracts
* adding new provider SDKs beyond current runtime substrate

### Dependencies

* existing provider/runtime substrate
* `P1X-I1`
* `P1X-I3`

### Acceptance criteria

* agents declare primary provider profile and allowed fallback profiles
* agents declare required capabilities
* orchestrator validates compatibility before execution
* provider selection remains package-owned and auditable
* capability mismatches fail explicitly

### Tests

* profile compatibility tests
* fallback selection tests
* unsupported-capability failure tests
* no-vendor-leak architecture checks for new public contracts

### Risks

* risk of provider-specific semantics bleeding upward
* risk of brittle capability mapping if under-specified

### Docs

* document agent provider-profile configuration and capability semantics

---

## 6. `P1X-I6 Implement handoff payload model + history sharing modes`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `priority:P1`, `status:ready`

### Summary

Implement structured-first handoff payload handling with optional natural-language notes and support for configurable history-sharing modes.

### Rationale

Downstream agents need focused, least-privilege context. The package should default to curated handoff payloads plus summary rather than unconstrained full-history sharing.

### Scope

* structured handoff payload validation
* optional handoff note support
* support:

  	* `payload_only`
  	* `payload_plus_summary`
  	* `full_history`
* default to `payload_plus_summary`

### Out of scope

* full transcript exposure by default
* semantic summarization redesign

### Dependencies

* `P1X-I4`

### Acceptance criteria

* handoff payloads are structured-first and validated
* natural-language notes are optional and supplemental
* history sharing modes are explicit and deterministic
* default mode is safe and least-privilege aligned

### Tests

* payload validation tests
* history-mode behavior tests
* trace assertions for handoff context propagation

### Risks

* risk of payloads becoming too loose
* risk of context bloat if full-history becomes the de facto path

### Docs

* document handoff payload semantics and history modes

---

## 7. `P1X-I7 Emit orchestration lifecycle events + redacted trace metadata`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:observability`, `priority:P1`, `status:ready`

### Summary

Emit package-owned orchestration lifecycle events for agent execution, delegation, handoff, provider selection, and orchestration completion, with redacted-by-default payloads.

### Rationale

Multi-agent orchestration without first-class telemetry becomes hard to debug and unsafe to operate. Events must remain package-owned and redacted by default.

### Scope

* emit events for:

  	* orchestration start
  	* agent execution start/completion
  	* delegation proposed/approved/rejected
  	* ownership transferred
  	* provider selected/failed over
  	* orchestration completed/failed
* preserve redacted metadata-only defaults

### Out of scope

* Pulse/Nightwatch UI
* external observability sinks

### Dependencies

* `P1X-I3`
* `P1X-I4`
* `P1X-I5`

### Acceptance criteria

* events are emitted at the correct orchestration boundaries
* payloads include orchestration and execution correlation identifiers
* raw user content and secrets are excluded by default
* events remain package-owned, not provider-SDK event classes

### Tests

* deterministic event fake tests
* redaction payload tests
* correlation propagation tests

### Risks

* risk of duplicate or confusing overlap with existing runtime telemetry
* risk of accidentally exposing raw content

### Docs

* update observability docs with orchestration-specific events

---

## 8. `P1X-I8 Package fakes + assertion helpers for orchestration flows`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:observability`, `priority:P1`, `status:ready`

### Summary

Extend the existing package fake/assertion layer to support orchestration-specific testing patterns such as delegation approval, ownership transfer, execution trees, and final orchestration results.

### Rationale

The package already has a testing harness. Multi-agent orchestration will add new high-value behavior that must remain easy to test without real provider calls.

### Scope

* orchestration test fakes/helpers
* assertion helpers for execution tree and handoff summary
* deterministic orchestration result assertions

### Out of scope

* replacing existing fake layers
* live provider integration tests

### Dependencies

* `P1X-I3`
* `P1X-I4`
* `P1X-I7`

### Acceptance criteria

* orchestration flows can be tested deterministically with package fakes
* assertion helpers cover common orchestration scenarios
* fake behavior aligns with real package semantics closely enough for regression testing

### Tests

* tests for the new fakes/assertions themselves
* comparison-style package-flow tests

### Risks

* risk of fake divergence from real orchestration semantics

### Docs

* update contributor guidance with orchestration testing patterns

---

## 9. `P1X-I9 TextToStructuredEvaluation blueprint over orchestrator`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:prompts`, `priority:P0`, `status:ready`

### Summary

Implement the first flagship blueprint, `TextToStructuredEvaluation`, as a package-owned multi-agent workflow that produces one final structured evaluation result from a single orchestration call.

### Rationale

This is one of the must-ship capabilities in `plan/SYSTEM-PROMPT.md`. It should be the first real workflow built on top of the orchestration core, proving the package can deliver opinionated AI
workflows rather than only low-level primitives.

### Scope

* entry coordinator agent
* internal specialist analysis/evaluation flow
* fixed top-level output DTO with configurable enabled dimensions
* prompt/version integration
* orchestration summary returned as one final result

### Out of scope

* arbitrary caller-defined output schemas
* unrestricted self-routing
* audio input

### Dependencies

* `P1X-I3`
* `P1X-I4`
* `P1X-I5`
* `P1X-I6`

### Acceptance criteria

* one blueprint call returns one final structured result
* internal specialist work is hidden behind orchestration
* output DTO is package-owned and stable
* dimensions are configurable within the fixed result schema
* no provider-specific payloads leak into public result contracts

### Tests

* deterministic blueprint execution tests using package fakes
* output-shape validation tests
* delegation trace tests for coordinator → specialist flow

### Risks

* risk of overfitting the first blueprint to one use case
* risk of unclear result-shape semantics if dimensions are under-specified

### Docs

* document public usage and output schema

---

## 10. `P1X-I10 Tests: TextToStructuredEvaluation deterministic multi-agent flow`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:test`, `area:core`, `priority:P1`, `status:ready`

### Summary

Add deterministic end-to-end coverage for `TextToStructuredEvaluation` across orchestration, provider selection, delegation, and final structured result assembly.

### Rationale

The first flagship workflow must be strongly regression-guarded because it validates the orchestration core and becomes a reference implementation for later workflows.

### Scope

* deterministic blueprint flow tests
* orchestration summary assertions
* provider profile routing assertions
* output DTO assertions

### Out of scope

* live provider calls
* benchmark/performance testing

### Dependencies

* `P1X-I9`

### Acceptance criteria

* full workflow is covered in deterministic tests
* internal agent delegation is regression-guarded
* final orchestration result is verified, not just one internal agent result

### Tests

* integration-style package tests with fakes only

### Risks

* risk of over-mocking if fake behavior is too shallow

### Docs

* none unless testing guidance needs expansion

---

## 11. `P1X-I11 AudioToTextToEvaluation blueprint over orchestrator`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:feature`, `area:core`, `area:prompts`, `priority:P1`, `status:ready`

### Summary

Implement `AudioToTextToEvaluation` as the second flagship workflow, composed over the same orchestration core and using transcription plus structured evaluation stages.

### Rationale

This is also explicitly must-ship in `plan/SYSTEM-PROMPT.md`. It should be implemented after the text-only blueprint so that transcription becomes a composition problem, not a fresh orchestration
design problem.

### Scope

* transcription stage
* analysis/evaluation orchestration
* one final structured orchestration result

### Out of scope

* live audio-provider dependency in tests
* waveform tooling/UI
* open-ended media pipelines

### Dependencies

* `P1X-I9`

### Acceptance criteria

* audio input can be transcribed and evaluated through one orchestration call
* final result remains package-owned and typed
* the workflow reuses orchestration primitives rather than bypassing them

### Tests

* deterministic multi-stage blueprint tests with fakes/stubs
* output-shape validation tests

### Risks

* risk of capability mismatches across transcription/evaluation provider profiles
* risk of making audio-specific concerns bleed into core orchestration

### Docs

* document audio blueprint usage and provider profile requirements

---

## 12. `P1X-I12 Docs: multi-agent orchestration architecture + workflow authoring`

**Milestone:** `P1X — Multi-Agent Orchestration & Flagship Blueprints`
**Labels:** `type:docs`, `area:docs`, `priority:P1`, `status:ready`

### Summary

Document the new orchestration architecture, agent authoring model, delegation semantics, provider-profile assignment, and flagship blueprint usage.

### Rationale

This capability changes the package’s core value proposition. It needs explicit docs for contributors and consumers before later release-hardening work.

### Scope

* architecture docs
* contributor guidance
* agent authoring guidance
* orchestration result semantics
* blueprint usage examples

### Out of scope

* marketing site content
* production demo app

### Dependencies

* `P1X-I3`
* `P1X-I9`
* `P1X-I11`

### Acceptance criteria

* architecture docs show the orchestration boundary clearly
* docs distinguish agent logic from provider adapters
* docs explain delegation vs handoff
* docs explain orchestration result structure
* examples avoid leaking provider-specific APIs into package public usage

### Tests

* documentation/snippet review
* snippet verification where practical

### Risks

* risk of docs drifting if written too early
* risk of under-documenting provider-profile behavior

### Docs

* this issue is itself the documentation vehicle

---

# Recommended execution order inside the new stack

1. `P1X-I1`
2. `P1X-I2`
3. `P1X-I3`
4. `P1X-I4`
5. `P1X-I5`
6. `P1X-I6`
7. `P1X-I7`
8. `P1X-I8`
9. `P1X-I9`
10. `P1X-I10`
11. `P1X-I11`
12. `P1X-I12`
