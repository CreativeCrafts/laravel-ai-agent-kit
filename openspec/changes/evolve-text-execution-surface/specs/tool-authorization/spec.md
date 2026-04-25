## ADDED Requirements

### Requirement: Two tool families are registered in separate registries

The package SHALL maintain two distinct tool registries: `InMemoryToolRegistry` (existing) for custom tools implementing the kit's `Tool` contract, and `ProviderToolRegistry` (new) for SDK-native provider tools such as `Laravel\Ai\Providers\Tools\WebSearch`, `WebFetch`, `FileSearch`, `ProviderTool`, and `Laravel\Ai\Tools\SimilaritySearch`. A name registered in one registry MUST NOT be visible from the other.

#### Scenario: Custom tool is not visible to the provider registry
- **WHEN** a custom `Tool` named `search` is registered in `InMemoryToolRegistry`
- **THEN** `ProviderToolRegistry::has('search')` MUST return `false`

#### Scenario: Provider tool is not visible to the custom registry
- **WHEN** a provider tool factory is registered in `ProviderToolRegistry` under the name `web-search.default`
- **THEN** `InMemoryToolRegistry::has('web-search.default')` MUST return `false`

### Requirement: ProviderToolRegistry stores factories, not instances

`ProviderToolRegistry` SHALL accept registration via a name and a factory closure, and MUST invoke the factory each time the tool is requested so each call receives an independent instance. Factories MUST return an instance whose class is either one of the SDK's provider tool classes or a subclass thereof.

#### Scenario: Factory is invoked per request
- **WHEN** a factory registered under `web-search.default` is retrieved twice from `ProviderToolRegistry`
- **THEN** the factory MUST be invoked twice, returning two distinct instances

#### Scenario: Unregistered name raises a typed exception
- **WHEN** `ProviderToolRegistry::get('missing')` is invoked and no factory is registered under that name
- **THEN** a typed `ProviderToolNotRegisteredException` MUST be raised with a message naming the missing tool

### Requirement: ToolAuthorizer contract discriminates between tool families

`ToolAuthorizer` SHALL expose two methods:
- `authorizeCustomTool(Tool $tool, array $input): bool` — preserves the existing authorizer shape so consumers' content-based policy logic ports with a simple rename.
- `authorizeProviderTool(string $providerToolName): bool` — simpler signature reflecting that provider tools execute server-side; authorizers typically gate by name alone.

`InMemoryToolRegistry::execute()` (for custom tools) and the provider-tool materializer MUST consult the corresponding method before materializing / executing each tool, and MUST raise the appropriate typed exception (`ToolUnauthorizedException` for custom, `ToolAuthorizationDeniedException` for provider) when authorization returns `false`.

#### Scenario: Custom tool authorization is consulted for InMemoryToolRegistry entries
- **WHEN** an `ExecutionRequest` carries `toolNames = ['search']` and the authorizer returns `false` for `authorizeCustomTool($tool, $input)`
- **THEN** the runtime MUST raise a typed authorization-denied exception before invoking the SDK

#### Scenario: Provider tool authorization is consulted for ProviderToolRegistry entries
- **WHEN** an `ExecutionRequest` carries `providerToolNames = ['web-search.default']` and the authorizer returns `false` for `authorizeProviderTool('web-search.default')`
- **THEN** the runtime MUST raise a typed authorization-denied exception before invoking the SDK

#### Scenario: Authorizer decisions on one family do not affect the other
- **WHEN** the authorizer returns `false` for `authorizeProviderTool('web-search.default')` and `true` for `authorizeCustomTool($tool, $input)`, and the request carries only `toolNames = ['search']`
- **THEN** the runtime MUST proceed normally without raising

### Requirement: DenyAllToolAuthorizer denies both families

`DenyAllToolAuthorizer` SHALL return `false` from both `authorizeCustomTool` and `authorizeProviderTool`. Provider tools MUST default to denied even though they execute server-side at the model provider, because they remain billable, rate-limited, and leak data to the provider.

#### Scenario: DenyAllToolAuthorizer denies custom tools
- **WHEN** `DenyAllToolAuthorizer::authorizeCustomTool($tool, $input)` is called with any tool and input
- **THEN** it MUST return `false`

#### Scenario: DenyAllToolAuthorizer denies provider tools
- **WHEN** `DenyAllToolAuthorizer::authorizeProviderTool('any-name')` is called
- **THEN** it MUST return `false`
