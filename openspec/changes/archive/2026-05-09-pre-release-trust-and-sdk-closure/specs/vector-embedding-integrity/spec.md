## ADDED Requirements

### Requirement: Namespace embedding width is consistent on upsert

Built-in `VectorStoreInterface` implementations (`DatabaseVectorStore`, `InMemoryVectorStore`) and the package test double `FakeVectorStore` MUST enforce a single embedding width per namespace. On `upsert`, when the namespace already contains at least one document, each new or updated document MUST have an embedding whose length equals that namespace’s established width. When the namespace is empty, the first document in the upsert batch MUST establish the width, and every other document in the same `upsert` call MUST match that width. Violations MUST surface as a throwable domain exception (the same family as existing vector operation failures) and MUST NOT persist partial writes for the violating call.

#### Scenario: Second upsert with mismatched width is rejected

- **WHEN** a namespace contains a document whose embedding has length N
- **AND** the caller invokes `upsert` with a document whose embedding length is not N
- **THEN** the implementation MUST throw before persisting the inconsistent document
- **AND** the previously stored documents for that namespace MUST remain unchanged

#### Scenario: First upsert in an empty namespace establishes width

- **WHEN** a namespace has no documents
- **AND** the caller invokes `upsert` with one or more documents whose embeddings all share the same length L
- **THEN** the implementation MUST persist all documents
- **AND** subsequent upserts for that namespace MUST require length L until the namespace becomes empty again

### Requirement: Vector search does not silently truncate mismatched embeddings

For built-in stores, when computing similarity between query and stored vectors within a single search operation, the implementation MUST NOT produce a numeric score by silently truncating to the shorter vector if lengths differ at runtime. If inconsistent rows exist due to legacy data, the implementation MUST either exclude those rows from scoring or fail the search with a clear error; the chosen behavior MUST be documented in code comments and `UPGRADE.md`.

#### Scenario: Search behavior with inconsistent legacy rows is explicit

- **WHEN** the store implementation reads a stored embedding whose length differs from the query embedding for the same search call
- **THEN** the implementation MUST NOT return a score computed from partial vector alignment without documentation-defined handling
- **AND** the documented handling MUST be one of: skip the row, or fail the operation with a throwable error

### Requirement: Fake vector store matches production contract

`CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore` MUST implement `CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreReferenceEmbedding` and MUST apply the same per-namespace embedding width rules as built-in stores so tests reflect production guarantees.

#### Scenario: Reference dimensions reflect stored documents

- **WHEN** `FakeVectorStore` contains documents in namespace `ns`
- **THEN** `referenceEmbeddingDimensions('ns')` MUST return the embedding length of the sampled stored document according to stable ordering rules consistent with built-in stores

#### Scenario: Dimension mismatch on upsert throws

- **WHEN** `FakeVectorStore` already holds a document with embedding length N in namespace `ns`
- **AND** a test calls `upsert` with a document whose embedding length is not N
- **THEN** `FakeVectorStore` MUST throw a vector operation exception
