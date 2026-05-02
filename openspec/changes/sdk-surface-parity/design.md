## Context

The package already exposes text runtime (`AiRuntime`, streaming), four modality runtimes (embeddings, image, transcription, reranking), memory, pipelines, orchestration, and `VectorStoreInterface` with an in-memory default. [docs/laravel-ai-sdk-capability-matrix.md](../../../docs/laravel-ai-sdk-capability-matrix.md) lists remaining gaps versus `laravel/ai` facades: **`Audio::`** (generation), **`Files::` / `Stores::`**, a **second vector driver**, optional **SimilaritySearch**, and **AgentKit** helpers.

## Goals

- Every roadmap row reaches **Covered** or an explicit **Won’t ship** with documentation substitute.
- New surfaces follow existing patterns: **contracts**, **DTOs**, **`config/ai-agent-kit.php`**, **`ConfigValidator`**, **service provider bindings**, **Pest tests**, **UPGRADE.md** / **CHANGELOG** / matrix updates.
- **Additive defaults:** existing apps keep behavior until they opt into new drivers or facade methods.

## Non-Goals

- Feature parity with every vendor-specific gateway option in one release.
- Deprecating SDK queue jobs; pipelines remain the kit-first async story.

## Decisions

### D1 — Implementation order (dependency-aware)

1. **Audio generation runtime** — Orthogonal to Files/Stores; unblocks matrix row 1; mirrors other modality packages.
2. **Vector retrieval parity** — Unlocks honest `vector.default_driver` and informs Files/Stores retrieval bridging.
3. **Laravel AI Files + Stores façade** — May delegate to SDK `Files` / `Stores` static APIs; must respect `SdkBackedVectorAdapterStrategy` (no raw SDK types leaking through `VectorStoreInterface`).
4. **SimilaritySearch-style tool** — After vector story is clear; either implement as opt-in tool or document **Won’t ship** + recipe.
5. **AgentKit facade ergonomics** — Last: thin delegates only, after contracts are stable.

### D2 — Audio generation

- **Contract:** `AudioGenerationRuntime` (name finalized in implementation) with request/result DTOs (text in, audio reference or bytes out—align with `Laravel\Ai\Audio::of()` / `PendingAudioGeneration` outcomes and `AudioResponse`).
- **Adapter:** `SdkAudioGenerationRuntime` calling the same code path the SDK uses (typically `Ai::fakeableAudioProvider` → generate). Respect `Lab` / provider profile naming consistent with other modalities.
- **Config:** `modalities.audio_generation.default_driver` = `sdk` | class-string.

### D3 — Files and Stores

- **Choice A (shipped):** **`LaravelAiFilesService`** and **`LaravelAiStoresService`** as package singletons mapping to `Files::get/put/putFromPath/putFromStorage/delete` and `Stores::create/get/delete` + `Store::add/remove/refresh`. Public APIs return **package DTOs** only.
- **Choice B:** Pipeline **step** types or **blueprint helpers** only—rejected as primary because the matrix calls for developer-facing coverage without forcing pipelines for CRUD.
- **Security:** No secrets in logs; redacted events optional (file id length, store id), consistent with observability rules.

### D4 — Vector parity

- **Minimum bar:** `ai-agent-kit.vector.default_driver` accepts at least **`in_memory`** and **one production-oriented driver** (e.g. **`sdk_store`** bridging provider store search/list, or **`database`** / pgvector-style if in-tree—**spike in implementation**). Validator + container binding must not throw for documented drivers.
- **Shipped (Phase 2):** **`database`** — `DatabaseVectorStore` persists `VectorDocument` embeddings in SQL (`ai_agent_vector_documents`); search uses the same in-process cosine similarity as `InMemoryVectorStore`. **Shipped (Phase 3):** **`LaravelAiStoresService`** wraps Laravel AI **`Stores`** for provider-hosted vector stores (distinct from `VectorStoreInterface`).
- **Alignment:** `SdkBackedVectorAdapterStrategy` boundary rules remain: `VectorStoreInterface` is authoritative; adapters map SDK/store results to `VectorDocument` / `VectorSearchResult`.

### D5 — SimilaritySearch

- **Shipped:** **`SimilaritySearchTool`** — package `Tool` that embeds the query via **`EmbeddingsRuntime`**, then searches **`VectorStoreInterface`**. Optional registration via `tools.similarity_search.enabled` / `register` (default **false**); name and embedding defaults are configurable. This complements Laravel AI’s Eloquent **`Laravel\Ai\Tools\SimilaritySearch`** (DB `whereVectorSimilarTo`) for apps using the kit vector store instead.

### D6 — AgentKit facade

- **Shipped:** **`AgentKitManager`** accepts the application **`Container`** and exposes thin methods: `executeStream`, `embed`, `transcribe`, `generateImage`, `rerank`, `generateAudio`, `laravelAiFiles`, `laravelAiStores` — each `make()`s the same contract binding as direct resolution. **`AgentKit`** facade `@method` annotations updated accordingly.

## Risks

- **Provider capability variance** — Audio and Stores require providers that implement `AudioProvider`, `FileProvider`, `StoreProvider`; document and test fail-closed errors.
- **SDK drift** — Pin and test against `laravel/ai` ^0.6; re-run matrix when upgrading.

## Migration

- Each phase updates **UPGRADE.md**, **CHANGELOG.md**, and **docs/laravel-ai-sdk-capability-matrix.md** status columns.
