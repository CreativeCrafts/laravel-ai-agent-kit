# Agents and orchestration

Agents are package-owned workflow participants. The orchestrator owns execution identity, delegation approval, lineage, provider-profile assignment, and final result assembly.

## Register agents explicitly

Agents are not auto-discovered. Register them through `AgentRegistry` in your application service provider:

~~~php
use App\Agents\CancellationAgent;
use App\Agents\CustomerSupportAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(AgentRegistry $agents): void
    {
        $agents->registerMany([
            CustomerSupportAgent::class,
            CancellationAgent::class,
        ]);
    }
}
~~~

## Define an agent

Each agent implements `CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent`.

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

## Run an orchestration

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

`OrchestrationResult` contains the orchestration ID, final agent, final execution ID, final structured output, compact summary, and trace records.

## Execution context

`AgentExecutionContext` gives the current agent:

- orchestration ID
- execution ID
- parent execution ID
- resolved `AgentDefinition`
- resolved provider profile
- task string
- structured payload
- metadata
- optional history summary

Read structured inputs with `payloadValue()` and `metadataValue()`. Return structured package-owned output; do not return provider-native payloads as your public workflow result.

## Result kinds

Use result kinds intentionally:

- `KIND_COMPLETE`: the agent finished successfully.
- `KIND_FAIL`: the agent finished unsuccessfully.
- `KIND_CONTINUE`: the same agent should continue with updated payload/state.
- `KIND_DELEGATE`: the orchestrator should evaluate a delegation proposal.

## Delegation

Delegation is orchestrator-governed. An agent may propose a downstream target, but the `DelegationPolicyEngine` decides whether that handoff is allowed.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

return new AgentExecutionResult(
    kind: AgentExecutionResult::KIND_DELEGATE,
    delegation: new DelegationProposal(
        mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
        targetAgent: 'refund.specialist',
        handoff: new HandoffPayload(
            task: 'Determine refund eligibility for the supplied subscription.',
            reason: 'The request requires refund-policy evaluation.',
            payload: ['subscription_id' => $context->payloadValue('subscription_id')],
            historyMode: HandoffPayload::HISTORY_PAYLOAD_PLUS_SUMMARY,
            note: 'Return a concise eligibility summary.',
            requestedOutcome: 'refund_eligibility',
        ),
    ),
    summary: 'Delegated refund evaluation.',
);
~~~

Delegation modes:

- `delegate_and_resume`: run the child agent, then resume the source agent.
- `transfer_control`: run the child agent and make it the terminal owner if it completes.

## Handoff payloads

Keep handoffs specific and minimal. Prefer curated structured payloads over broad upstream state.

History modes:

- `payload_only`
- `payload_plus_summary`
- `full_history`

Use full history sparingly because smaller handoffs are easier to test and safer for privacy.

## Provider-profile assignment

Agents declare `requiredCapabilities`, `primaryProviderProfile`, and `fallbackProviderProfiles`. The package selects the first enabled compatible profile.

If no compatible profile exists, the package throws a typed package exception with attempt details.

See [Providers](providers.md).

## Trace semantics

The trace list is the public lineage surface. Each trace record includes execution IDs, parent-child relationships, agent key, provider profile, result kind, optional target agent, summary, and safe metadata.

Use traces for diagnostics and tests. Do not depend on provider-native request/response payloads for orchestration assertions.

## Testing

Use package fakes for deterministic orchestration tests. See [Testing](testing.md).
