## ADDED Requirements

### Requirement: PromptBlueprint exposes a fluent method for generation options

`PromptBlueprint` SHALL expose a `withGenerationOptions(GenerationOptions|null)` method that returns a new immutable blueprint carrying the supplied options. `PromptBlueprintCompiler` MUST thread the options through to the resulting `ExecutionRequest::$generationOptions` verbatim.

#### Scenario: Default blueprint has no generation options
- **WHEN** `LaravelAiAgentKit::prompt('name')` is called without invoking `withGenerationOptions`
- **THEN** the resulting `PromptBlueprint` MUST have `generationOptions` equal to `null` and the compiled `ExecutionRequest` MUST have `generationOptions` equal to `null`

#### Scenario: Options are compiled into the request
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->withGenerationOptions(new GenerationOptions(temperature: 0.1))`
- **THEN** the compiled `ExecutionRequest` MUST carry a `GenerationOptions` whose `temperature` equals `0.1`

### Requirement: PromptBlueprint exposes a fluent method for structured-output schema

`PromptBlueprint` SHALL expose `withSchema(Closure|ObjectSchema|string|null)` accepting a `Closure` matching the `HasStructuredOutput::schema()` signature, an `ObjectSchema` instance, a class-string of a class implementing `HasStructuredOutput`, or `null` to clear. `PromptBlueprintCompiler` MUST thread the value through to `ExecutionRequest::$schema` without transformation; all normalization to the SDK-consumed `Closure` shape happens inside `SdkAiRuntime`, not on the blueprint.

#### Scenario: Default blueprint has no schema
- **WHEN** `LaravelAiAgentKit::prompt('name')` is called without invoking `withSchema`
- **THEN** the compiled `ExecutionRequest` MUST have `schema` equal to `null`

#### Scenario: Closure schema passes through to the request
- **WHEN** a blueprint is built with `withSchema($closure)` where `$closure` matches the `HasStructuredOutput::schema()` signature
- **THEN** the compiled `ExecutionRequest` MUST have `schema` referring to the same `Closure` instance

#### Scenario: Schema class-string passes through to the request
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->withSchema(MyEvaluationSchema::class)`
- **THEN** the compiled `ExecutionRequest` MUST have `schema` equal to the string `MyEvaluationSchema::class`

#### Scenario: ObjectSchema instance passes through to the request
- **WHEN** a blueprint is built with `withSchema($objectSchema)` where `$objectSchema` is an `ObjectSchema` instance
- **THEN** the compiled `ExecutionRequest` MUST have `schema` referring to the same `ObjectSchema` instance

### Requirement: PromptBlueprint exposes fluent methods for attachments

`PromptBlueprint` SHALL expose `withAttachment(Laravel\Ai\Files\File)` returning a new blueprint with the attachment appended, and `withAttachments(list<Laravel\Ai\Files\File>)` returning a new blueprint with the list replaced. `PromptBlueprintCompiler` MUST thread the attachment list through to `ExecutionRequest::$attachments` preserving order.

#### Scenario: Default blueprint has no attachments
- **WHEN** `LaravelAiAgentKit::prompt('name')` is called without adding attachments
- **THEN** the compiled `ExecutionRequest` MUST have `attachments` equal to the empty list

#### Scenario: withAttachment appends in order
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->withAttachment($image1)->withAttachment($image2)`
- **THEN** the compiled `ExecutionRequest` MUST have `attachments` equal to `[$image1, $image2]` in that exact order

#### Scenario: withAttachments replaces the list
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->withAttachment($image1)->withAttachments([$image2])`
- **THEN** the compiled `ExecutionRequest` MUST have `attachments` equal to `[$image2]` only, not `[$image1, $image2]`

### Requirement: PromptBlueprint exposes fluent methods for provider tools

`PromptBlueprint` SHALL expose `addProviderTool(string)` returning a new blueprint with the tool name appended, and `withProviderTools(list<string>)` returning a new blueprint with the list replaced. Existing `addTool` and `withTools` methods MUST continue to apply only to custom tools. `PromptBlueprintCompiler` MUST thread the two families into `ExecutionRequest::$toolNames` and `ExecutionRequest::$providerToolNames` respectively, with no cross-contamination.

#### Scenario: Default blueprint has empty provider tool list
- **WHEN** `LaravelAiAgentKit::prompt('name')` is called without adding provider tools
- **THEN** the compiled `ExecutionRequest` MUST have `providerToolNames` equal to the empty list

#### Scenario: addProviderTool does not affect custom toolNames
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->addTool('search')->addProviderTool('web-search.default')`
- **THEN** the compiled `ExecutionRequest` MUST have `toolNames = ['search']` and `providerToolNames = ['web-search.default']`, each list containing exactly one entry

#### Scenario: withProviderTools replaces the provider tool list only
- **WHEN** a blueprint is built as `LaravelAiAgentKit::prompt('name')->addTool('search')->addProviderTool('web-fetch.default')->withProviderTools(['web-search.default'])`
- **THEN** the compiled `ExecutionRequest` MUST have `toolNames = ['search']` and `providerToolNames = ['web-search.default']`

### Requirement: PromptBlueprint remains immutable across all new builder methods

Every newly-added builder method on `PromptBlueprint` (`withGenerationOptions`, `withSchema`, `withAttachment`, `withAttachments`, `addProviderTool`, `withProviderTools`) SHALL return a new `PromptBlueprint` instance without mutating the receiver, matching the existing convention of all other `with*` methods on the class.

#### Scenario: Builder methods do not mutate the receiver
- **WHEN** a blueprint `$a = LaravelAiAgentKit::prompt('name')` is derived into `$b = $a->withGenerationOptions(new GenerationOptions(temperature: 0.5))`
- **THEN** `$a->generationOptions` MUST remain `null` after the call, and `$b->generationOptions->temperature` MUST equal `0.5`

### Requirement: AgentKitManager exposes single-prompt execution through the recommended injection point

`AgentKitManager` SHALL expose a `run(PromptBlueprint): ExecutionResult` method that delegates to the container-bound `BlueprintRunner`. The `AgentKit` facade SHALL expose the method via a `@method static ExecutionResult run(PromptBlueprint $blueprint)` annotation. Consumers injecting `AgentKitManager` MUST be able to execute any `PromptBlueprint` without resolving `BlueprintRunner` separately.

#### Scenario: Manager delegates to BlueprintRunner
- **WHEN** `AgentKitManager::run($blueprint)` is invoked with a `PromptBlueprint`
- **THEN** the manager MUST invoke `BlueprintRunner::run($blueprint)` exactly once with the same blueprint instance, and MUST return the `ExecutionResult` produced by the runner without modification

#### Scenario: Facade delegates to the manager
- **WHEN** `AgentKit::run($blueprint)` is invoked
- **THEN** the facade MUST resolve `AgentKitManager` from the container and invoke the manager's `run()` method with the supplied blueprint

#### Scenario: Manager does not mutate the blueprint it receives
- **WHEN** `AgentKitManager::run($blueprint)` is invoked and the call completes
- **THEN** the supplied `PromptBlueprint` instance MUST retain all its original field values (the immutability contract is preserved through the manager)

#### Scenario: Container-resolved manager exposes the new dependency
- **WHEN** `AgentKitManager` is resolved via `app(AgentKitManager::class)`
- **THEN** it MUST be constructed with `TextToStructuredEvaluation`, `AudioToTextToEvaluation`, `AgentOrchestrator`, and `BlueprintRunner` — four dependencies total
