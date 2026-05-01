## ADDED Requirements

### Requirement: A second vector driver SHALL be selectable without container errors

`ai-agent-kit.vector.default_driver` SHALL accept at least one documented driver in addition to `in_memory` that is suitable for non-ephemeral use (implementation choice: SDK-backed store bridge, database-backed store, or other—see `design.md`).

#### Scenario: Documented driver resolves

- **WHEN** `default_driver` is set to a driver name documented in `config/ai-agent-kit.php` and README
- **THEN** `app(VectorStoreInterface::class)` resolves successfully when that driver’s required configuration is present.

#### Scenario: Config validator rejects invalid driver

- **WHEN** `default_driver` is set to an unknown driver name
- **THEN** validation fails at boot with a clear error (consistent with existing `ConfigValidator` patterns).

### Requirement: Adapter boundary rules SHALL be preserved

Any SDK-backed retrieval MUST conform to `SdkBackedVectorAdapterStrategy`: `VectorStoreInterface` remains the public port; adapters map to `VectorDocument` and `VectorSearchResult` collections.

### Requirement: Documentation SHALL be updated

README vector section and `docs/laravel-ai-sdk-capability-matrix.md` SHALL move the vector row from **Partial** to **Covered** for the shipped driver story.
