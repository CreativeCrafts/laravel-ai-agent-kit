# Orchestration and blueprints

The package orchestration boundary is package-owned and provider-neutral:

- callers submit one `OrchestrationRequest` to `AgentOrchestrator`
- agents implement package contracts and return package-owned `AgentExecutionResult` values
- provider selection is resolved from each agent's declared provider profiles and required capabilities
- delegation policy and handoff semantics are enforced by the orchestrator rather than by individual agents
- the final `OrchestrationResult` exposes one orchestration ID, one final owner, one final output payload, one summary, and a lineage trace

For the full architecture, authoring model, delegation and handoff rules, provider-profile assignment behavior, and flagship workflow guidance, see
[`MULTI_AGENT_ORCHESTRATION.md`](../MULTI_AGENT_ORCHESTRATION.md).

## `TextToStructuredEvaluation` blueprint

Run the package-owned `TextToStructuredEvaluation` blueprint when you want one structured evaluation result from one orchestration call while keeping the internal coordinator-to-specialist flow hidden
behind the public API:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateText(
    new TextToStructuredEvaluationRequest(
        subject: 'support reply',
        text: 'We can refund the unused portion of your subscription within five business days.',
        enabledDimensions: ['clarity', 'accuracy', 'completeness'],
        promptVersion: '1.0.0',
    ),
);

$summary = $result->summary;
$recommendedAction = $result->recommendedAction;
$clarityScore = $result->dimension('clarity')?->score;
~~~

The blueprint returns a fixed package-owned result schema with:

- `summary`
- `recommendedAction`
- `confidence`
- `enabledDimensions`
- `dimensions` keyed by dimension name, each with `score`, `summary`, and `evidence`
- `orchestrationSummary`, `finalAgent`, `promptName`, and `promptVersion`

- `structuredEvaluationPath` (`structured_output` when the runtime returned typed structured output, or `text_normalization` when the kit fell back to parsing model text)
- `structuredEvaluationRepaired` (true when the text fallback path repaired wrapped or embedded JSON)

The enabled dimensions are caller-configurable, but the top-level result contract remains package-owned and stable.

Before running the blueprint, register the prompt template referenced by `promptName` and `promptVersion`. The specialist stage requests structured output from the runtime using a package-owned JSON
schema; if the provider does not populate structured output, the kit falls back to the same bounded text normalization used previously.

## `AudioToTextToEvaluation` blueprint

Run the package-owned `AudioToTextToEvaluation` blueprint when you want one orchestration call to transcribe audio first and then evaluate the resulting transcript through the same structured
evaluation pipeline:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateAudio(
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        audioMimeType: 'audio/wav',
        enabledDimensions: ['clarity', 'accuracy'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
    ),
);

$transcript = $result->transcript;
$summary = $result->summary;
~~~

The audio blueprint returns a fixed package-owned result schema that extends the text evaluation shape with audio-specific fields:

- `audioReference`
- `transcript`
- `summary`
- `recommendedAction`
- `confidence`
- `enabledDimensions`
- `dimensions` keyed by dimension name, each with `score`, `summary`, and `evidence`
- `transcriptionPromptName`, `transcriptionPromptVersion`, `evaluationPromptName`, and `evaluationPromptVersion`
- `orchestrationSummary` and `finalAgent`

Provider profiles for the audio blueprint must be compatible with both stages:

- the transcription stage requires a provider profile that supports `audio_transcription`
- the evaluation stage requires a provider profile that supports `structured_output`

Register both prompt templates before execution. The transcription stage returns one transcript string (plain text from the runtime, not a separate structured-output schema). The evaluation
stage uses the same structured evaluation path as `TextToStructuredEvaluation` (runtime schema plus bounded text fallback).

## Multi-agent orchestration API

Run orchestrated multi-agent flows through the package agent orchestrator:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::orchestrate(
    new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle a support refund workflow',
        input: ['subscription_id' => 'sub-123'],
    ),
);
~~~

`OrchestrationResult` stays package-owned. The stable surface is:

- `orchestrationId` for the whole orchestration run
- `finalAgent` and `finalExecutionId` for the terminal owner and terminal execution node
- `finalOutput` for the package-owned workflow payload
- `summary` for the compact orchestration-level summary
- `trace` for execution lineage, including `executionId`, `parentExecutionId`, `agentKey`, `providerProfile`, `resultKind`, optional `targetAgent`, and safe metadata

Delegation semantics are explicit:

- `delegate_and_resume` sends work to a child agent and then resumes the parent agent after the child finishes
- `transfer_control` hands ownership to the child agent, making the delegated agent the final owner if it completes the workflow

## See also

- [Pipelines, queues, memory, and vectors](pipelines-queues-and-memory.md)
- [Configuration reference](configuration.md)
- [README quick start](../README.md#usage)
