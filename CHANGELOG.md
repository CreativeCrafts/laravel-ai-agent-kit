# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [Unreleased]

### Added

- Modality runtime contracts (`TranscriptionRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, `RerankingRuntime`) with request/result DTOs under `Core\Modality` and SDK-backed implementations (`Sdk*` classes).
- `ai-agent-kit.modalities` configuration for per-modality `default_driver` (`sdk` or custom class-string).
- `AudioToTextToEvaluation` transcription stage uses `TranscriptionRuntime` when `audio_reference` is decodable base64 or a data URI; falls back to the existing prompt + `AiRuntime` path otherwise.
- Runtime budget enforcement for `max_tokens`, `max_tool_calls`, and `max_cost_usd` in the SDK runtime bridge via `RuntimeBudgetEnforcer`.
- Typed runtime budget exceptions with explicit failure reasons for exceeded ceilings and invalid/missing cost metadata.
- Runtime budget regression coverage in `tests/SdkAiRuntimeTest.php`.
- `SECURITY.md` with private vulnerability reporting guidance and supported-version policy.

### Changed

- Runtime now includes `estimated_cost_usd` metadata in `ExecutionResult` when provided in request metadata.
- Governance catalog IDs for multi-agent roadmap items were aligned from `P10-I*` to `P1X-I*`, and flagship blueprint statuses were aligned to `status:ready` in roadmap/catalog metadata.
- README now documents enforced runtime budget behavior and the exact in-memory tool-schema validation subset.
