## 1. Provider resolution

- [x] 1.1 Add tests for runtime execution when provider/model are omitted and Agent Kit default provider policy supplies them.
- [x] 1.2 Add tests for explicit provider/model preserving caller intent.
- [x] 1.3 Implement effective provider/model resolution for direct runtime execution.
- [x] 1.4 Ensure blueprint and orchestration paths inherit the same effective provider behavior through the runtime.

## 2. Prompt failover execution

- [x] 2.1 Add tests where the first provider fails and the second provider succeeds.
- [x] 2.2 Add tests where all configured providers fail and the final failure is normalized.
- [x] 2.3 Add tests proving schema, tools, provider tools, attachments, generation options, and timeout are preserved across attempts.
- [x] 2.4 Implement prompt execution attempt loop using `FailoverProviderSelector`.
- [x] 2.5 Ensure only provider-failure categories trigger provider failover.
- [x] 2.6 Ensure memory reconciliation happens once, using the successful response only.

## 3. Circuit breaker integration

- [x] 3.1 Add tests where open breakers skip providers during failover.
- [x] 3.2 Record provider failure for failed eligible attempts.
- [x] 3.3 Record provider success for the winning attempt.
- [x] 3.4 Preserve existing `ProviderSkippedByCircuitBreaker`, `ProviderFailoverResolved`, and `ProviderFailoverExhausted` event behavior.

## 4. Streaming failover

- [x] 4.1 Decide and document streaming policy: creation-only failover, no mid-stream failover.
- [x] 4.2 Add tests for stream creation failure followed by successful failover before chunks are emitted.
- [x] 4.3 Add tests proving mid-stream provider errors emit a terminal `StreamFailure` and do not retry after chunks.
- [x] 4.4 Implement streaming failover behavior according to the documented policy.

## 5. Observability and metadata

- [x] 5.1 Include attempted providers and final provider in execution metadata where safe.
- [x] 5.2 Include failover exhaustion context in terminal failure metadata/events.
- [x] 5.3 Ensure all telemetry remains redacted and does not include prompts, attachments, API keys, or tool payloads.

## 6. Documentation

- [x] 6.1 Update `docs/providers.md` with runtime failover behavior and opt-in/opt-out guidance.
- [x] 6.2 Update `docs/production.md` with provider failover and circuit breaker operational notes.
- [x] 6.3 Update `CHANGELOG.md` with the behavior change.

## 7. Validation

- [x] 7.1 Run `openspec validate add-runtime-provider-failover-execution`.
- [x] 7.2 Run formatting checks.
- [x] 7.3 Run PHPStan/static analysis.
- [x] 7.4 Run relevant runtime/provider/failover test subsets.
- [x] 7.5 Run the full test suite if feasible.
