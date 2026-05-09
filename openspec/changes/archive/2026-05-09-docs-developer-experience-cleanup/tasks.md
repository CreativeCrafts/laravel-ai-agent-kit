## 1. Documentation inventory and API verification

- [x] 1.1 Re-read the root README, the former root multi-agent orchestration guide, and all current files under `docs/` from latest `main` before implementation.
- [x] 1.2 Inspect source files and tests needed to verify documented public APIs, config keys, facade methods, request/result DTOs, contracts, driver names, and publish tags.
- [x] 1.3 Identify public developer docs versus maintainer-only docs and produce the final move/rename map before editing.

## 2. README rewrite

- [x] 2.1 Rewrite `README.md` as a concise developer-facing landing page.
- [x] 2.2 Include installation, publish/migration commands, minimal configuration, quick text evaluation, quick audio evaluation, and basic orchestration examples.
- [x] 2.3 Add a short core concepts map with links to task-focused docs.
- [x] 2.4 Keep security/privacy defaults visible: default-deny tools, redacted telemetry, explicit memory persistence, provider-neutral public contracts.
- [x] 2.5 Remove maintainer-only references from the primary README path, including CI matrix, release verification, SDK parity inventory, roadmap completion notes, and implementation-history language.

## 3. Public documentation restructuring

- [x] 3.1 Create or rewrite `docs/getting-started.md` for first successful package usage.
- [x] 3.2 Rewrite `docs/configuration.md` as a focused configuration reference and index rather than a combined manual for every subsystem.
- [x] 3.3 Create or rewrite `docs/providers.md` for provider profiles, capability-based selection, failover basics, and preset usage.
- [x] 3.4 Create or rewrite `docs/blueprints.md` for `TextToStructuredEvaluation` and `AudioToTextToEvaluation` request/result usage.
- [x] 3.5 Create or rewrite `docs/agents-and-orchestration.md` for custom agents, registration, orchestration, delegation, handoffs, provider-profile assignment, and traces.
- [x] 3.6 Create or rewrite `docs/prompts.md` for prompt repositories, prompt versions, variables, and prompt usage in workflows.
- [x] 3.7 Create or rewrite `docs/tools.md` for tool registration, schema validation, default-deny authorization, provider tools, and safe execution.
- [x] 3.8 Create or rewrite `docs/memory.md` for in-memory, database, Redis, retention, encryption, no-store, and conversation continuation usage.
- [x] 3.9 Create or rewrite `docs/pipelines-and-queues.md` for sync pipelines, queued pipelines, `RunContext`, payload sizing, and result handlers.
- [x] 3.10 Create or rewrite `docs/vectors-and-retrieval.md` for `VectorStoreInterface`, database/in-memory vector stores, provider Files/Stores, `FileSearch`, and `SimilaritySearchTool` boundaries.
- [x] 3.11 Create or rewrite `docs/streaming-and-modalities.md` for streaming runtime, transcription, embeddings, image generation, reranking, and audio generation.
- [x] 3.12 Create or rewrite `docs/errors-and-telemetry.md` for failure categories, typed exceptions, package events, and redaction behavior.
- [x] 3.13 Create or rewrite `docs/testing.md` for application-developer testing with package fakes and deterministic examples.
- [x] 3.14 Create or rewrite `docs/production.md` for production checklist, queues, memory persistence, vectors, tool authorization, encryption, telemetry, and operational warnings.

## 4. Maintainer documentation relocation

- [x] 4.1 Create `docs/maintainers/`.
- [x] 4.2 Move CI matrix content to `docs/maintainers/ci-matrix.md`.
- [x] 4.3 Move release verification content to `docs/maintainers/release-verification.md`.
- [x] 4.4 Move SDK capability matrix content to `docs/maintainers/sdk-capability-matrix.md` or replace it with a shorter public SDK relationship guide if any public guidance remains necessary.
- [x] 4.5 Move SDK async inventory content to `docs/maintainers/sdk-async-inventory.md`, while preserving user-facing queue guidance in `docs/pipelines-and-queues.md`.
- [x] 4.6 Move contributor testing doctrine to `docs/maintainers/testing-strategy.md`, while preserving application testing guidance in `docs/testing.md`.
- [x] 4.7 Update `CONTRIBUTING.md` to link maintainer docs.

## 5. Multi-agent orchestration cleanup

- [x] 5.1 Fold useful public content from the former root multi-agent orchestration guide into `docs/agents-and-orchestration.md`.
- [x] 5.2 Move flagship blueprint details from the former root orchestration guide into `docs/blueprints.md`.
- [x] 5.3 Delete the root multi-agent orchestration document after redirecting links to `docs/agents-and-orchestration.md` and `docs/blueprints.md`.

## 6. Changelog and history cleanup

- [x] 6.1 Move issue-history and implementation-record language from public docs into `CHANGELOG.md` where relevant.
- [x] 6.2 Ensure public docs do not contain roadmap issue labels, OpenSpec workflow references, implementation-artifact labels, or archived-change references.
- [x] 6.3 Preserve upgrade-relevant behavior changes in changelog or upgrade notes rather than public onboarding docs.

## 7. Documentation quality checks

- [x] 7.1 Update or add documentation developer-experience tests for public-doc boundaries.
- [x] 7.2 Add assertions that public docs exclude internal markers such as `implementation artifact`, `OpenSpec`, `P0-I`, `P1Y-I`, `roadmap complete`, and `archived under openspec`.
- [x] 7.3 Allow those internal markers only in `CHANGELOG.md`, `CONTRIBUTING.md`, `docs/maintainers/**`, `openspec/**`, and `plan/**`.
- [x] 7.4 Add or update tests that verify public documentation links resolve after moves/renames.
- [x] 7.5 Add or update tests that verify key documented classes/config keys exist where practical.

## 8. Validation and closure

- [x] 8.1 Run `openspec validate docs-developer-experience-cleanup`.
- [x] 8.2 Run formatting and test checks required by the repository for documentation changes.
- [x] 8.3 Review the final documentation path as a new Laravel developer: install, configure, run first workflow, understand production constraints, and find advanced guides without reading maintainer history.
