## ADDED Requirements

### Requirement: Structured evaluation SHALL consume runtime structured output directly
`TextToStructuredEvaluation` execution MUST use runtime schema-based structured output and MUST not rely on JSON string extraction heuristics as the primary success path.

#### Scenario: Structured payload returned by runtime
- **WHEN** evaluation execution is run with a structured schema
- **THEN** the evaluation result is built from `ExecutionResult.structuredOutput` without text parsing fallback

### Requirement: Evaluation SHALL provide bounded fallback behavior
If structured output is unavailable due to provider/runtime limitations, the evaluation flow MUST provide a bounded fallback path with explicit observability signaling.

#### Scenario: Structured output missing at runtime
- **WHEN** runtime completes without `structuredOutput`
- **THEN** evaluation emits a fallback-path signal and returns a deterministic failure or fallback result according to configured policy
