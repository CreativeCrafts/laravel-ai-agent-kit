## ADDED Requirements

### Requirement: Runtime SHALL execute middleware pipeline deterministically
The runtime MUST execute registered middleware in a deterministic order around execution dispatch, preserving request mutations and response transformations.

#### Scenario: Middleware execution order is preserved
- **WHEN** multiple runtime middleware components are registered
- **THEN** the runtime invokes them in configured order before dispatch and reverse-unwinds after dispatch

### Requirement: Middleware SHALL observe and propagate execution failures
The runtime MUST allow middleware to observe execution failures and MUST preserve original failure semantics when middleware does not explicitly transform the exception.

#### Scenario: Failure passes through middleware stack
- **WHEN** runtime execution fails and middleware performs only observation
- **THEN** the original failure is propagated to the caller without semantic change

### Requirement: Middleware SHALL be shared across execution surfaces
The package MUST apply middleware consistently for direct runtime calls and orchestration/blueprint-driven runtime calls.

#### Scenario: Blueprint and direct calls share middleware behavior
- **WHEN** equivalent requests are executed via blueprint runner and direct runtime APIs
- **THEN** both execution surfaces invoke the same middleware pipeline with consistent behavior