# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [Unreleased]

### Added

- `TextToStructuredEvaluationResult` exposes `structuredEvaluationPath` and `structuredEvaluationRepaired` (and `toArray()` includes them) so callers can see whether the specialist used runtime `structuredOutput` or text normalization.
- `StructuredEvaluationOutputNormalizer::normalizeFromDecodedArray()` for validating structured payloads without round-tripping through JSON strings.
- `StructuredEvaluationJsonSchema` (`Core\Runtime`) as the stable `ObjectSchema` handle passed on specialist `ExecutionRequest` instances.
- `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware` and `TerminatingRuntimeMiddleware`; optional ordered stack under `ai-agent-kit.runtime.middleware`.
- `MiddlewareExecutingAiRuntime` wraps the SDK runtime when middleware is configured; `ExecutionRequest::withMetadata` and `ExecutionResult::withMetadata` for middleware helpers.
- Runtime budget enforcement for `max_tokens`, `max_tool_calls`, and `max_cost_usd` in the SDK runtime bridge via `RuntimeBudgetEnforcer`.
- Typed runtime budget exceptions with explicit failure reasons for exceeded ceilings and invalid/missing cost metadata.
- Runtime budget regression coverage in `tests/SdkAiRuntimeTest.php`.
- `SECURITY.md` with private vulnerability reporting guidance and supported-version policy.

### Changed

- `AiRuntime` resolves to `MiddlewareExecutingAiRuntime` around `SdkAiRuntime` when `runtime.middleware` lists one or more middleware classes (blueprints and orchestration use the same binding).
- `TextToStructuredEvaluation` specialist now requests structured output via `ExecutionRequest::$schema`, prefers `ExecutionResult::$structuredOutput` when valid, and falls back to the existing text normalizer when structured output is missing or invalid; coordinator forwards path flags into the final blueprint result.
- README documents the new observability fields and clarifies that the audio transcription stage remains plain text from the runtime.
- Runtime now includes `estimated_cost_usd` metadata in `ExecutionResult` when provided in request metadata.
- Governance catalog IDs for multi-agent roadmap items were aligned from `P10-I*` to `P1X-I*`, and flagship blueprint statuses were aligned to `status:ready` in roadmap/catalog metadata.
- README now documents enforced runtime budget behavior and the exact in-memory tool-schema validation subset.
