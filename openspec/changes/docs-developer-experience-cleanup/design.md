## Context

A detailed review of the root README, the former root multi-agent orchestration guide, and every current file under `docs/` found that the documentation has strong technical coverage but weak developer experience because it mixes public onboarding, advanced extension guidance, maintainer process, SDK parity inventory, release verification, issue-history notes, and implementation-artifact language.

The package needs a documentation structure that teaches the package by developer workflow first, architecture second, and internals last.

## Goals / Non-Goals

**Goals:**

- Make `README.md` a short, useful package landing page for Laravel developers.
- Organize public docs by developer tasks rather than internal milestones.
- Keep maintainer-only documents discoverable without putting them in the primary onboarding path.
- Preserve accurate package-owned boundary language: Laravel AI SDK is the runtime substrate, while Agent Kit owns workflows, contracts, policy, memory, telemetry, and developer-facing APIs.
- Remove issue IDs, OpenSpec references, roadmap-completion statements, and implementation-artifact language from public developer docs.
- Move historical implementation context to `CHANGELOG.md`, `CONTRIBUTING.md`, `docs/maintainers/**`, or archived OpenSpec artifacts.
- Add documentation tests or guardrails so public docs stay developer-facing.

**Non-Goals:**

- Changing runtime behavior, package contracts, service provider bindings, configuration semantics, memory behavior, vector behavior, provider policy, or telemetry behavior.
- Rewriting implementation history inside public docs.
- Creating marketing-only copy that hides important security, privacy, or production constraints.
- Removing maintainer documentation entirely.

## Decisions

### D1 — README becomes the developer landing page

`README.md` should be optimized for first contact and early success. It should include:

- package positioning
- installation
- minimal configuration
- first text evaluation example
- first audio evaluation example
- first orchestration/agent example
- core concept map
- security and privacy defaults
- links to task-focused docs

It should not include long release-checklist references, SDK parity inventories, CI matrix details, or implementation-history notes.

### D2 — Public docs are task-oriented

Public docs should be organized around tasks:

- getting started
- configuration
- providers and provider profiles
- blueprints
- agents and orchestration
- prompts
- tools
- memory
- pipelines and queues
- vectors and retrieval
- streaming and modalities
- errors and telemetry
- testing
- production

Each public guide should answer:

1. What problem does this solve?
2. When should I use it?
3. What is the smallest working example?
4. What config is required?
5. What can go wrong?
6. Where do I go next?

### D3 — Maintainer docs move under `docs/maintainers/**`

The following kinds of documents should not be on the primary developer path:

- CI matrix details
- release verification checklists
- SDK capability inventories
- SDK async/job inventory
- package testing doctrine for contributors
- implementation-history records

They should move under `docs/maintainers/**` and be linked from `CONTRIBUTING.md`.

### D4 — Multi-agent orchestration becomes a public guide under `docs/`

The former root multi-agent orchestration guide contained valuable public guidance, but that guidance should live in `docs/agents-and-orchestration.md` instead of a root-level document.

The rewritten guide should retain:

- agent authoring model
- agent registration
- orchestration request/result semantics
- delegation and handoff semantics
- provider-profile assignment rules
- trace semantics

It should remove or relocate excessive internal adapter-boundary exposition and flagship blueprint details that belong in `docs/blueprints.md`.

### D5 — Changelog owns issue and implementation history

Public docs should not say that a page is an implementation artifact for an issue or that a roadmap phase is complete. That history belongs in `CHANGELOG.md` and archived OpenSpec changes.

### D6 — Documentation tests enforce public-doc boundaries

The package should include tests or static checks that keep public docs clean. Public docs should not contain internal markers such as:

- `implementation artifact`
- `OpenSpec`
- issue labels like `P0-I`, `P1Y-I`, or similar roadmap codes
- `roadmap complete`
- `archived under openspec`

Those markers may remain in `CHANGELOG.md`, `CONTRIBUTING.md`, `docs/maintainers/**`, `openspec/**`, and `plan/**`.

## Proposed Public Documentation Structure

```text
docs/
  getting-started.md
  configuration.md
  providers.md
  blueprints.md
  agents-and-orchestration.md
  prompts.md
  tools.md
  memory.md
  pipelines-and-queues.md
  vectors-and-retrieval.md
  streaming-and-modalities.md
  errors-and-telemetry.md
  testing.md
  production.md
```

## Proposed Maintainer Documentation Structure

```text
docs/maintainers/
  ci-matrix.md
  release-verification.md
  sdk-capability-matrix.md
  sdk-async-inventory.md
  testing-strategy.md
```

## Risks / Trade-offs

- **Risk: broken links after moving docs.** Mitigate with link checks or documentation tests.
- **Risk: examples drift from public APIs.** Mitigate by verifying documented classes, methods, and config keys against source during implementation.
- **Risk: losing important safety details.** Mitigate by keeping security defaults and production constraints in README summaries and dedicated production docs.
- **Risk: hiding maintainer knowledge.** Mitigate by moving, not deleting, maintainer docs and linking them from `CONTRIBUTING.md`.

## Migration Plan

1. Create the new public documentation structure.
2. Move maintainer-only material to `docs/maintainers/**`.
3. Rewrite README as the concise developer landing page.
4. Split overloaded guides into focused task pages.
5. Fold the former root multi-agent orchestration content into `docs/agents-and-orchestration.md` and remove the root document.
6. Move issue-history details to `CHANGELOG.md`.
7. Update `CONTRIBUTING.md` to link maintainer docs.
8. Update documentation developer-experience tests.
9. Verify links and example API references.

## Open Questions

- Should `docs/laravel-ai-sdk-capability-matrix.md` remain public as an advanced reference, or move entirely to `docs/maintainers/sdk-capability-matrix.md` with a shorter public `docs/laravel-ai-sdk.md` replacement?
