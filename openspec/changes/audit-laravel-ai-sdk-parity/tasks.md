## 1. SDK source and docs inventory

- [x] 1.1 Identify the exact installed `laravel/ai` version used for the audit.
- [x] 1.2 Review Laravel AI SDK docs for current agents, tools, files, stores, vectors, modalities, async jobs, middleware, failover, events, and testing fakes.
- [x] 1.3 Review installed SDK source for capabilities not obvious in docs.
- [x] 1.4 Record SDK version/date in maintainer docs.

## 2. Capability matrix update

- [x] 2.1 Update `docs/maintainers/sdk-capability-matrix.md` with every audited SDK surface.
- [x] 2.2 Classify each surface as package-owned, direct-SDK, deferred, or out of scope.
- [x] 2.3 Add rationale for direct-SDK and deferred classifications.
- [x] 2.4 Link follow-up OpenSpec changes for package-owned gaps that require implementation.

## 3. Async inventory update

- [x] 3.1 Update `docs/maintainers/sdk-async-inventory.md` with all current SDK jobs.
- [x] 3.2 Confirm Agent Kit queued pipeline guidance remains accurate for package workflows.
- [x] 3.3 Document when SDK jobs are preferred over Agent Kit queued pipelines.

## 4. Events and observability inventory

- [x] 4.1 Inventory current Laravel AI SDK events.
- [x] 4.2 Map events to package-normalized redacted events where they exist.
- [x] 4.3 Classify unwrapped events as package-owned, direct-SDK, deferred, or out of scope.
- [x] 4.4 Add follow-up proposals for high-value event normalization gaps.

## 5. Public documentation updates

- [x] 5.1 Update relevant public docs with Agent Kit versus direct Laravel AI SDK guidance.
- [x] 5.2 Clarify SDK queue jobs versus Agent Kit queued pipelines.
- [x] 5.3 Clarify SDK vector stores versus Agent Kit application-owned vector stores.
- [x] 5.4 Clarify provider failover expectations after the failover execution proposal is implemented.

## 6. Testing and fake parity

- [x] 6.1 Compare `AgentKitManager`/facade methods against available package fakes.
- [x] 6.2 Add tests for missing fakes where the package already owns the surface.
- [x] 6.3 Document direct-SDK testing guidance for surfaces intentionally not wrapped.

## 7. Follow-up planning

- [x] 7.1 Create or update OpenSpec proposals for package-owned gaps discovered during the sweep.
- [x] 7.2 Mark low-value or intentionally direct-SDK gaps as documented decisions.
- [x] 7.3 Update `CHANGELOG.md` for notable documentation/process changes.

## 8. Validation

- [x] 8.1 Run `openspec validate audit-laravel-ai-sdk-parity`.
- [x] 8.2 Run formatting checks if docs tooling requires it.
- [x] 8.3 Run relevant documentation tests.
- [x] 8.4 Run fake parity tests if code/test changes are included.
