<?php

declare(strict_types=1);

arch('it will not use debugging functions')
  ->expect(['dd', 'dump', 'ray'])
  ->each->not->toBeUsed();

arch('public contracts do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Contracts')
  ->not->toUse('Laravel\\Ai');

arch('public blueprints do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Blueprints')
  ->not->toUse('Laravel\\Ai')
  // PromptBlueprint intentionally accepts SDK Files\File attachments and
  // Laravel\Ai\ObjectSchema as ergonomic schema inputs — see the
  // evolve-text-execution-surface change (design decisions D2, D4).
  ->ignoring('CreativeCrafts\\LaravelAiAgentKit\\Blueprints\\PromptBlueprint');

arch('public vector contracts and strategy types do not depend on laravel ai sdk types')
  ->expect([
    'CreativeCrafts\\LaravelAiAgentKit\\Contracts\\Vector',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\Exceptions',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorDocument',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorSearchQuery',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorSearchResult',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\SdkBackedVectorAdapterStrategy',
  ])
  ->not->toUse('Laravel\\Ai');

arch('public observability events do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Observability\\Events')
  ->not->toUse('Laravel\\Ai');

arch('public testing fakes do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Testing\\Fakes')
  ->not->toUse('Laravel\\Ai');

arch('public testing assertions do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Testing\\Assertions')
  ->not->toUse('Laravel\\Ai');

arch('public agent contracts and dto surfaces do not depend on laravel ai sdk types')
  ->expect([
    'CreativeCrafts\\LaravelAiAgentKit\\Contracts\\Agents',
    'CreativeCrafts\\LaravelAiAgentKit\\Contracts\\Orchestration',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Agents\\AgentDefinition',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Agents\\AgentExecutionContext',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Agents\\AgentExecutionResult',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\ConfigurableDelegationPolicyEngine',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\DelegationPolicyDecision',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\DelegationPolicyMode',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\DelegationProposal',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\ExecutionTraceRecord',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\HandoffPayload',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\OrchestrationRequest',
    'CreativeCrafts\\LaravelAiAgentKit\\Core\\Orchestration\\OrchestrationResult',
  ])
  ->not->toUse('Laravel\\Ai');
