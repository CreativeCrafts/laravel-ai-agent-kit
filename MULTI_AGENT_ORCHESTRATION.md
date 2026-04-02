# Multi-Agent Orchestration and Workflow Authoring

This guide documents the package-owned orchestration architecture, the agent authoring model, delegation and handoff semantics, provider-profile assignment rules, and the two flagship workflows that
currently ship with the package.

## Why this boundary exists

Laravel AI Agent Kit exposes multi-agent workflows through package-owned contracts and DTOs. The package intentionally keeps provider SDK details behind internal bridges so workflow code, tests, and
public blueprint results do not depend on vendor-specific request or response shapes.

At a high level:

- application code or a package blueprint submits one `OrchestrationRequest` to `CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator`
- the orchestrator resolves agents through `CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry`
- each agent declares routing requirements through `CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition`
- provider-profile selection is resolved through `CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector`
- delegation approval is resolved through `CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\DelegationPolicyEngine`
- orchestration lifecycle events are emitted with redacted metadata through package observability events
- the orchestrator returns one package-owned `OrchestrationResult`

This means the orchestrator, not the participating agents, owns execution identity, lineage, delegation approval, and final result assembly.

## Architecture overview

The synchronous orchestration flow currently looks like this:

1. Create one `OrchestrationRequest` with an `entryAgent`, `task`, optional structured `input`, optional `metadata`, and optional `conversationId`.
2. `SynchronousAgentOrchestrator` creates one orchestration ID for the entire run and dispatches `OrchestrationStarted`.
3. The orchestrator resolves the entry agent from the registry and determines the effective provider profile for that agent.
4. The agent receives an `AgentExecutionContext` containing the orchestration ID, a new execution ID, parent lineage, the resolved provider profile, the task, structured payload, metadata, and any
   available history summary.
5. The agent returns an `AgentExecutionResult` in one of four package-owned forms: `complete`, `fail`, `continue`, or `delegate`.
6. The orchestrator records an `ExecutionTraceRecord` for each execution node and, when relevant, emits `OrchestrationDelegated`, `OrchestrationCompleted`, or `OrchestrationFailed`.
7. The orchestrator returns one final `OrchestrationResult` containing the terminal owner, terminal execution ID, terminal output payload, orchestration summary, and full lineage trace.

### Public boundary versus internal adapter boundary

Keep this separation explicit:

- **Public/package boundary:** `Agent`, `AgentRegistry`, `AgentOrchestrator`, `OrchestrationRequest`, `OrchestrationResult`, `AgentDefinition`, `AgentExecutionContext`, `AgentExecutionResult`,
  `DelegationProposal`, `HandoffPayload`, provider registries/selectors, and blueprint request/result DTOs.
- **Internal adapter boundary:** runtime bridge classes such as `AiRuntime`, prompt repositories and mappers, provider registry implementations, and concrete orchestration policy implementations.

A custom workflow should depend on package-owned orchestration contracts. A leaf agent may depend on package internals such as `AiRuntime` or `PromptExecutionMapper`, but the workflow itself should
not surface those details.

## Agent authoring model

Each agent implements `CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent`. That contract has two responsibilities:

- `definition()`: declare the agent's stable identity and routing requirements
- `handle()`: execute the task against the supplied context and return one package-owned `AgentExecutionResult`

### Defining an agent

`AgentDefinition` is the authoritative declaration for orchestration-level routing. It contains:

- `key`: stable orchestration-facing identifier
- `displayName`: descriptive label for logs, traces, and diagnostics
- `requiredCapabilities`: capabilities that the selected provider profile must satisfy
- `primaryProviderProfile`: first provider profile to try
- `fallbackProviderProfiles`: ordered fallback profile names
- `delegationTargets`: explicit agent keys that the agent may delegate to under static policy mode

A minimal specialist agent looks like this:

~~~php
<?php

declare(strict_types=1);

namespace App\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class RefundSpecialistAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'refund.specialist',
            displayName: 'Refund Specialist',
            requiredCapabilities: ['structured_output'],
            primaryProviderProfile: 'openai-structured',
            fallbackProviderProfiles: ['anthropic-structured'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
                'refund_status' => 'eligible',
                'subscription_id' => $context->payloadValue('subscription_id'),
            ],
            summary: 'Refund specialist determined eligibility.',
        );
    }
}
~~~

Register agents explicitly through the package `AgentRegistry`; agents are not auto-discovered. The package-owned registry is the source of truth for orchestration-time lookup.

### Handling context safely

`AgentExecutionContext` provides:

- `orchestrationId`: one ID shared across the entire orchestration
- `executionId`: one ID for the current execution node
- `parentExecutionId`: nullable lineage pointer to the parent execution
- `agent`: the resolved `AgentDefinition`
- `providerProfile`: the resolved provider profile name for this execution
- `task`: the agent-specific task string
- `payload`: structured handoff or workflow data
- `metadata`: orchestration metadata
- `historySummary`: optional compact summary propagated from earlier steps

Use `payloadValue()` and `metadataValue()` to read structured inputs. Keep outputs structured and package-owned; avoid returning raw vendor payloads.

### Choosing the correct result kind

Use the four result kinds intentionally:

- `KIND_COMPLETE`: the agent has finished successfully and owns the terminal output payload
- `KIND_FAIL`: the agent has finished unsuccessfully and the final orchestration status should become `failed`
- `KIND_CONTINUE`: the same agent should be invoked again with its output merged into the payload
- `KIND_DELEGATE`: the orchestrator should evaluate a `DelegationProposal` and potentially invoke another agent

`KIND_CONTINUE` is for same-agent state progression. It is not a substitute for delegating to another specialist.

## Delegation and handoff semantics

Delegation is always orchestrator-governed. An agent may propose a downstream target, but the orchestrator remains the final authority by evaluating the proposal through `DelegationPolicyEngine`.

### Delegation modes

`DelegationProposal` supports two explicit modes:

- `delegate_and_resume`: run the delegated agent, then return control to the source agent. The source agent remains the final owner if it completes after resuming.
- `transfer_control`: run the delegated agent and make it the final owner if it completes the workflow.

A coordinator-style agent can delegate like this:

~~~php
<?php

declare(strict_types=1);

namespace App\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class SupportCoordinatorAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'support.coordinator',
            displayName: 'Support Coordinator',
            requiredCapabilities: [],
            primaryProviderProfile: 'openai-default',
            delegationTargets: ['refund.specialist'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
                targetAgent: 'refund.specialist',
                handoff: new HandoffPayload(
                    task: 'Determine refund eligibility for the supplied subscription.',
                    reason: 'The request requires refund-policy evaluation.',
                    payload: [
                        'subscription_id' => $context->payloadValue('subscription_id'),
                    ],
                    historyMode: HandoffPayload::HISTORY_PAYLOAD_PLUS_SUMMARY,
                    note: 'Return a concise eligibility summary for the coordinator.',
                    requestedOutcome: 'refund_eligibility',
                ),
            ),
            summary: 'Support coordinator delegated the refund evaluation.',
        );
    }
}
~~~

### Handoff payload design

`HandoffPayload` is structured-first. It contains:

- `task`: the exact downstream task
- `reason`: why the handoff is happening
- `payload`: structured data required by the downstream agent
- `historyMode`: how much prior context should flow downstream
- `note`: optional summary or instruction for the child agent
- `requestedOutcome`: optional shorthand for the expected downstream result

Prefer handoff payloads that are specific, minimal, and explicit. Do not pass broad upstream state unless the child truly needs it.

### History-sharing modes

The package currently supports three history modes:

- `payload_only`: the child agent receives only the handoff payload and preserved conversation identifier metadata
- `payload_plus_summary`: the child agent receives the handoff payload plus any existing compact history summary, optionally replaced by the handoff note
- `full_history`: the child agent inherits the parent's orchestration metadata in addition to the handoff payload

Use `full_history` sparingly. The package intentionally defaults toward curated payloads and summaries because they are easier to reason about, safer for privacy, and more stable for testing.

### Delegation policy modes

`ConfigurableDelegationPolicyEngine` supports three package-owned policy modes:

- `static_only`: only the agent's declared `delegationTargets` are allowed
- `dynamic_with_allowlist`: declared targets plus explicit config allowlists are allowed
- `dynamic_full_registry`: any registered agent may become a delegation target

Policy rewrites may transparently replace one proposed target with another. Trace metadata records both the effective target and the original proposed target when a rewrite occurs.

Configuration lives under `config/ai-agent-kit.php`:

~~~php
'orchestration' => [
    'delegation_policy' => [
        'mode' => 'static_only',
        'allowlist' => [],
        'rewrites' => [],
    ],
],
~~~

## Provider-profile assignment rules

Provider selection for an agent is declarative and ordered:

1. The agent declares a `primaryProviderProfile`.
2. The agent may declare ordered `fallbackProviderProfiles`.
3. The selector checks each declared profile in order.
4. A profile is eligible only if it is defined, enabled, and satisfies every capability listed in `requiredCapabilities`.
5. The first compatible profile wins.
6. If no declared profile is compatible, the selector throws a typed `NoCompatibleAgentProviderProfileException` with attempt details.

This keeps provider routing policy out of workflow code. A workflow or blueprint should reason about capabilities such as `structured_output` or `audio_transcription`, not about vendor-specific model
APIs.

### Capability examples

The shipped blueprints use capabilities this way:

- text evaluation specialists require `structured_output`
- audio transcription specialists require `audio_transcription`
- coordinator agents can remain capability-light and delegate to specialized stages

When you add a new agent, declare capabilities that matter to workflow behavior, not implementation trivia.

## Orchestration result semantics

`OrchestrationResult` is the stable package-owned result for a multi-agent run. It contains:

- `orchestrationId`: one identifier for the whole workflow
- `status`: `completed`, `failed`, or `cancelled`
- `finalAgent`: the terminal owner of the workflow
- `finalExecutionId`: the execution node that produced the terminal output
- `finalOutput`: the terminal structured payload
- `summary`: the compact orchestration-level summary
- `trace`: a list of `ExecutionTraceRecord` instances representing execution lineage

Each `ExecutionTraceRecord` contains:

- `orchestrationId`
- `executionId`
- `parentExecutionId`
- `agentKey`
- `providerProfile`
- `resultKind`
- optional `targetAgent`
- optional `summary`
- safe `metadata`

The trace list is the public lineage surface. Parent-child execution relationships can be reconstructed using `parentExecutionId`. The package does not expose provider SDK responses in this result.

## Flagship workflows

The package currently ships two package-owned blueprints that sit on top of the orchestration core.

### `TextToStructuredEvaluation`

Purpose: evaluate text and return one package-owned structured result.

Internal flow:

1. the blueprint ensures its package agents are registered
2. the coordinator agent receives the caller request
3. the coordinator delegates to the specialist agent
4. the specialist uses prompt rendering plus runtime execution to obtain structured output
5. the coordinator resumes, normalizes the specialist payload, and returns one final package-owned result DTO

Public request surface:

- `subject`
- `text`
- `enabledDimensions`
- optional `promptName`, `promptVersion`, `promptVariables`
- optional `conversationId`, `storeConversation`, `continueConversation`, `model`, and freeform metadata

Public result surface:

- `subject`, `summary`, `recommendedAction`, `confidence`
- `enabledDimensions`
- `dimensions` keyed by dimension name, each with `score`, `summary`, and `evidence`
- `orchestrationSummary`, `finalAgent`, `promptName`, `promptVersion`, and `trace`

The specialist expects prompt-managed output that can be validated into the package-owned evaluation schema.

### `AudioToTextToEvaluation`

Purpose: transcribe audio and then evaluate the transcript through the same structured evaluation shape.

Internal flow:

1. the audio coordinator agent receives the caller request
2. the coordinator delegates to the transcription specialist
3. the transcription specialist uses a provider profile with `audio_transcription` capability
4. the coordinator resumes with the transcript and delegates into the structured evaluation flow
5. the final result extends the text evaluation shape with audio-specific fields

Additional request fields include:

- `audioReference`
- optional `audioMimeType`
- transcription prompt name, version, variables, and optional model override
- evaluation prompt name, version, variables, and optional model override

Additional result fields include:

- `audioReference`
- `transcript`
- `transcriptionPromptName` and `transcriptionPromptVersion`
- `evaluationPromptName` and `evaluationPromptVersion`

Provider-profile requirements are staged: transcription must resolve to a profile that supports `audio_transcription`, and evaluation must resolve to a profile that supports `structured_output`.

## Workflow authoring guidance

When you add a new orchestration workflow or blueprint, follow these rules:

1. Keep the public request and result DTOs package-owned.
2. Use the orchestrator as the only control plane; do not call child agents directly from application code.
3. Keep coordinator agents thin. They should validate routing inputs, decide whether to continue or delegate, and normalize the final package-owned result.
4. Keep provider-specific execution inside leaf specialists or adapter services.
5. Prefer explicit capabilities and provider profiles over hard-coded vendor/model choices in workflow code.
6. Use typed failure paths and explicit summaries so final orchestration failures are diagnosable.
7. Favor deterministic, structured payloads in tests and docs.

## Testing and maintenance guidance

Use package fakes for orchestration-level tests and Laravel AI SDK fakes only when the integration boundary itself is under test. Keep traces small and explicit so parent-child ownership remains
obvious in assertions.

When orchestration contracts, result semantics, or flagship workflows change, update this document together with `README.md` and `CONTRIBUTING.md`. The docs should always describe the package-owned
boundary rather than provider-specific implementation details.