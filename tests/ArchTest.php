<?php

declare(strict_types=1);

test('it will not use debugging functions', function (): void {
    $paths = [__DIR__, dirname(__DIR__) . '/src'];

    foreach ($paths as $path) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            expect($file->getPathname())->not->toContain('/vendor/');
            expect(file_get_contents($file->getPathname()))
              ->not->toMatch('/\\b(?:dd|dump|ray)\\s*\\(/');
        }
    }
});

arch('public contracts do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Contracts')
  ->not->toUse('Laravel\\Ai');

test('public blueprints do not depend on laravel ai sdk types', function (): void {
    $allowedFiles = [
      dirname(__DIR__) . '/src/Blueprints/Agents/TextToStructuredEvaluationSpecialistAgent.php',
      dirname(__DIR__) . '/src/Blueprints/PromptBlueprint.php',
    ];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/Blueprints'));

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || $file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        if (in_array($file->getPathname(), $allowedFiles, true)) {
            continue;
        }

        expect(file_get_contents($file->getPathname()))
          ->not->toContain('Laravel\\Ai');
    }
});

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
