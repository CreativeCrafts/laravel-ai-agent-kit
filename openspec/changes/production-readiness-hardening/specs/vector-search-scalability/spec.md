## ADDED Requirements

### Requirement: Database vector search complexity SHALL be documented

The package SHALL document that `DatabaseVectorStore::search` loads documents for a namespace and scores in-process, with **time and memory** scaling roughly linearly in the number of rows for that namespace.

#### Scenario: Operator documentation exists

- **WHEN** an operator reads `UPGRADE.md` or the capability matrix vector section
- **THEN** they find explicit **O(n) per namespace** guidance and when to use a custom `VectorStoreInterface` implementation.

### Requirement: Optional database vector scan limit SHALL be configurable

The package SHALL support an optional configuration key that limits how many rows are read from SQL per `search` call, with documented **semantic trade-offs** (e.g. approximate top-K when the cap is lower than namespace cardinality).

#### Scenario: Default preserves full scan

- **WHEN** the optional limit is not configured
- **THEN** `DatabaseVectorStore::search` behavior SHALL match the pre-change semantics (full namespace iteration).

#### Scenario: Configured limit is honored

- **WHEN** a positive integer limit is configured and the implementation applies it
- **THEN** the store SHALL not read unbounded rows beyond that limit for a single search call, and documentation SHALL state ordering/limitation implications.
