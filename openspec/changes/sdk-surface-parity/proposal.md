## Why

[docs/laravel-ai-sdk-capability-matrix.md](../../../docs/laravel-ai-sdk-capability-matrix.md) inventories Laravel AI SDK capabilities against Agent Kit. Several high-value surfaces remain **planned** or **partial**: audio **generation** (TTS), provider **`Files` / `Stores`** (uploads and provider vector stores), **vector driver parity** beyond `in_memory`, an optional **SimilaritySearch-style** retrieval tool, and **facade ergonomics** so developers can stay on package patterns instead of calling `Laravel\Ai\*` directly.

This change tracks **full implementation** of those roadmap priorities in dependency order, with explicit specs and acceptance tests.

## What Changes

- **Audio generation modality:** Contract + SDK-backed runtime mirroring `EmbeddingsRuntime` / `TranscriptionRuntime`, config under `ai-agent-kit.modalities`, validation, tests, and matrix/docs updates.
- **Laravel AI Files and Stores:** Package-owned façade or services for provider file CRUD and store lifecycle (create/get/add/remove/delete), aligned with `SdkBackedVectorAdapterStrategy` boundaries; config, redacted events where appropriate, tests.
- **Vector / retrieval parity:** At least one additional `VectorStoreInterface` driver selectable via `ai-agent-kit.vector.default_driver` (e.g. SDK-backed store retrieval bridge or database-backed implementation per design); honest README/config; tests.
- **Similarity search tool (optional):** Either a packaged tool that composes with `ToolAuthorizer` and mirrors SDK `SimilaritySearch` semantics where feasible, or a documented **decision not to ship** with a first-party custom-tool recipe—either outcome closes the matrix row.
- **AgentKit facade:** Convenience methods for `StreamingAiRuntime` and modality runtimes (resolve from container, delegate to contracts), PHPDoc and tests; no duplication of orchestration logic inside the facade.

## Capabilities

### New Capabilities (delta specs under `specs/`)

- `audio-generation-runtime`
- `laravel-ai-files-stores`
- `vector-retrieval-parity`
- `similarity-search-tool`
- `agentkit-facade-ergonomics`

## Impact

- **Public API:** New contracts, DTOs, config keys, optional `AgentKit` methods.
- **Breaking risk:** Low if defaults preserve current behavior and new drivers are opt-in via `default_driver` / modality keys.
- **Dependencies:** `laravel/ai` ^0.6; concrete SDK calls finalized in `design.md` per capability.

## Non-Goals

- Replacing Laravel AI or re-implementing provider gateways.
- Guaranteeing every provider supports every modality (document support matrix and fail-closed errors).
- Unifying SDK queue jobs with pipelines into a single implementation (remain **parallel** models; document when to use which).
