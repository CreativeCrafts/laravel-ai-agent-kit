## ADDED Requirements

### Requirement: The SimilaritySearch gap SHALL be closed explicitly

The change MUST either:

- **Ship** a package-integrated similarity search tool that works with `ToolAuthorizer` and documents registration, **or**
- **Document “won’t ship”** with a maintainer decision note in `design.md`, `docs/laravel-ai-sdk-capability-matrix.md`, and a developer recipe (custom tool registration using `Laravel\Ai\Tools\SimilaritySearch` or `VectorStoreInterface`).

#### Scenario: Shipped tool has tests

- **WHEN** the ship option is chosen
- **THEN** Pest tests cover authorization denial, successful search, and deterministic behavior under fakes.

#### Scenario: No-ship has a recipe

- **WHEN** the document option is chosen
- **THEN** the matrix and a `docs/` snippet show the recommended pattern without leaving the row ambiguous.
