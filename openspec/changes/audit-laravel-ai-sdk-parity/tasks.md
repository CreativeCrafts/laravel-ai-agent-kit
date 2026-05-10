## 1. SDK source and docs inventory

- [ ] 1.1 Identify the exact installed `laravel/ai` version used for the audit.
- [ ] 1.2 Review Laravel AI SDK docs for current agents, tools, files, stores, vectors, modalities, async jobs, middleware, failover, events, and testing fakes.
- [ ] 1.3 Review installed SDK source for capabilities not obvious in docs.
- [ ] 1.4 Record SDK version/date in maintainer docs.

## 2. Capability matrix update

- [ ] 2.1 Update `docs/maintainers/sdk-capability-matrix.md` with every audited SDK surface.
- [ ] 2.2 Classify each surface as package-owned, direct-SDK, deferred, or out of scope.
- [ ] 2.3 Add rationale for direct-SDK and deferred classifications.
- [ ] 2.4 Link follow-up OpenSpec changes for package-owned gaps that require implementation.

## 3. Async inventory update

- [ ] 3.1 Update `docs/maintainers/sdk-async-inventory.md` with all current SDK jobs.
- [ ] 3.2 Confirm Agent Kit queued pipeline guidance remains accurate for package workflows.
- [ ] 3.3 Document when SDK jobs are preferred over Agent Kit queued pipelines.

## 4. Events and observability inventory

- [ ] 4.1 Inventory current Laravel AI SDK events.
- [ ] 4.2 Map events to package-normalized redacted events where they exist.
- [ ] 4.3 Classify unwrapped events as package-owned, direct-SDK, deferred, or out of scope.
- [ ] 4.4 Add follow-up proposals for high-value event normalization gaps.

## 5. Public documentation updates

- [ ] 5.1 Update relevant public docs with Agent Kit versus direct Laravel AI SDK guidance.
- [ ] 5.2 Clarify SDK queue jobs versus Agent Kit queued pipelines.
- [ ] 5.3 Clarify SDK vector stores versus Agent Kit application-owned vector stores.
- [ ] 5.4 Clarify provider failover expectations after the failover execution proposal is implemented.

## 6. Testing and fake parity

- [ ] 6.1 Compare `AgentKitManager`/facade methods against available package fakes.
- [ ] 6.2 Add tests for missing fakes where the package already owns the surface.
- [ ] 6.3 Document direct-SDK testing guidance for surfaces intentionally not wrapped.

## 7. Follow-up planning

- [ ] 7.1 Create or update OpenSpec proposals for package-owned gaps discovered during the sweep.
- [ ] 7.2 Mark low-value or intentionally direct-SDK gaps as documented decisions.
- [ ] 7.3 Update `CHANGELOG.md` for notable documentation/process changes.

## 8. Validation

- [ ] 8.1 Run `openspec validate audit-laravel-ai-sdk-parity`.
- [ ] 8.2 Run formatting checks if docs tooling requires it.
- [ ] 8.3 Run relevant documentation tests.
- [ ] 8.4 Run fake parity tests if code/test changes are included.
