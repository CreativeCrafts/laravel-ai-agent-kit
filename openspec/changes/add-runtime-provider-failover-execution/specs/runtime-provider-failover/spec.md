## ADDED Requirements

### Requirement: Runtime SHALL resolve an effective provider before execution
The runtime MUST resolve an effective provider and model for every execution attempt using request values first and Agent Kit provider policy second.

#### Scenario: Request omits provider
- **WHEN** a runtime request omits provider information
- **THEN** Agent Kit resolves the provider from configured provider policy before invoking Laravel AI SDK

#### Scenario: Request specifies provider
- **WHEN** a runtime request specifies a provider
- **THEN** Agent Kit uses that provider as the first execution attempt

### Requirement: Runtime SHALL fail over eligible provider failures
The runtime MUST retry eligible provider failures using configured failover order until a provider succeeds or no eligible providers remain.

#### Scenario: First provider fails and second provider succeeds
- **WHEN** the first provider attempt fails with an eligible provider failure
- **AND** another enabled provider remains in failover order
- **THEN** the runtime retries with the next provider
- **AND** returns the successful response from the winning provider

#### Scenario: Failover is exhausted
- **WHEN** all eligible provider attempts fail
- **THEN** the runtime emits normalized failure telemetry
- **AND** surfaces the final terminal runtime failure to the caller

### Requirement: Runtime SHALL preserve request semantics across failover attempts
Provider failover MUST preserve schema, tools, provider tools, attachments, generation options, timeout, memory projection, budgets, and request metadata across attempts.

#### Scenario: Structured request fails over
- **WHEN** a structured-output request fails over from one provider to another
- **THEN** the same schema and request options are used for the successful attempt

### Requirement: Runtime SHALL integrate failover with circuit breaker policy
The runtime MUST use configured circuit breaker failover policy when selecting candidate providers and MUST record provider success/failure outcomes when circuit breaker support is enabled.

#### Scenario: Candidate provider has open breaker
- **WHEN** a candidate provider is blocked by an open circuit breaker
- **THEN** the runtime skips that provider
- **AND** emits the configured provider skipped event

### Requirement: Streaming failover SHALL be creation-only
Streaming execution MUST retry failover only when stream creation fails before any chunk is emitted. Provider errors after chunk emission MUST become a terminal stream failure and MUST NOT retry on another provider.

#### Scenario: Stream creation fails before chunks
- **WHEN** stream creation fails before any chunk is emitted
- **THEN** the runtime may fail over to the next eligible provider

#### Scenario: Stream fails after chunks
- **WHEN** a stream emits at least one chunk and later fails
- **THEN** the runtime emits one terminal stream failure
- **AND** does not retry against another provider
