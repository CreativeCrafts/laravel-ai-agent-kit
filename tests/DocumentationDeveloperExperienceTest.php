<?php

declare(strict_types=1);

it('links readme github action badges to workflows that exist in the repository', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/ci.yml')
      ->toContain('github.com/creativecrafts/laravel-ai-agent-kit/actions/workflows/ci.yml')
      ->not->toContain('run-tests.yml')
      ->not->toContain('fix-php-code-style-issues.yml');
});

it('keeps the readme focused on developer onboarding', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('## Installation')
      ->toContain('## Minimal configuration')
      ->toContain('## Quick start: evaluate text')
      ->toContain('TextToStructuredEvaluation $evaluation')
      ->toContain('## Quick start: evaluate audio')
      ->toContain('AgentKit::evaluateAudio(')
      ->toContain('## Quick start: register and orchestrate agents')
      ->toContain('AgentKit::orchestrate(')
      ->toContain('## Core concepts')
      ->toContain('[Providers](docs/providers.md)')
      ->toContain('[Production](docs/production.md)')
      ->not->toContain('Maintainers map Laravel AI SDK features')
      ->not->toContain('Before tagging releases');
});

it('documents security and privacy defaults in the readme', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('Tool execution is default-deny')
      ->toContain('Telemetry is redacted by default')
      ->toContain('Conversation persistence is explicit')
      ->toContain('Provider SDK details stay behind package-owned contracts and DTOs');
});

it('provides the focused public documentation guides linked from the readme', function (): void {
    foreach ([
        'docs/getting-started.md',
        'docs/configuration.md',
        'docs/providers.md',
        'docs/blueprints.md',
        'docs/agents-and-orchestration.md',
        'docs/prompts.md',
        'docs/tools.md',
        'docs/memory.md',
        'docs/pipelines-and-queues.md',
        'docs/vectors-and-retrieval.md',
        'docs/streaming-and-modalities.md',
        'docs/errors-and-telemetry.md',
        'docs/testing.md',
        'docs/production.md',
    ] as $path) {
        expect(file_exists(repositoryPath($path)))->toBeTrue($path . ' should exist.');
    }
});

it('uses dependency injection in the focused developer guides', function (): void {
    expect(documentationContents('docs/blueprints.md'))
      ->toContain('private TextToStructuredEvaluation $evaluation');

    expect(documentationContents('docs/pipelines-and-queues.md'))
      ->toContain('private PipelineRunner $runner')
      ->toContain('private QueuedPipelineDispatcher $dispatcher')
      ->not->toContain('app(PipelineRunner::class)')
      ->not->toContain('app(QueuedPipelineDispatcher::class)');

    expect(documentationContents('docs/memory.md'))
      ->toContain('private ConversationStore $store')
      ->not->toContain('app(ConversationStore::class)');

    expect(documentationContents('docs/vectors-and-retrieval.md'))
      ->toContain('private VectorStoreInterface $vectorStore')
      ->not->toContain('app(VectorStoreInterface::class)');
});

it('keeps maintainer documentation under the maintainer docs path', function (): void {
    foreach ([
        'docs/maintainers/ci-matrix.md',
        'docs/maintainers/release-verification.md',
        'docs/maintainers/sdk-capability-matrix.md',
        'docs/maintainers/sdk-async-inventory.md',
        'docs/maintainers/testing-strategy.md',
    ] as $path) {
        expect(file_exists(repositoryPath($path)))->toBeTrue($path . ' should exist.');
    }

    expect(documentationContents('CONTRIBUTING.md'))
      ->toContain('docs/maintainers/ci-matrix.md')
      ->toContain('docs/maintainers/release-verification.md')
      ->toContain('docs/maintainers/testing-strategy.md');
});

it('keeps internal implementation-history markers out of public developer docs', function (): void {
    $publicPaths = [
        'README.md',
        'MULTI_AGENT_ORCHESTRATION.md',
        'docs/configuration.md',
        'docs/getting-started.md',
        'docs/providers.md',
        'docs/blueprints.md',
        'docs/agents-and-orchestration.md',
        'docs/prompts.md',
        'docs/tools.md',
        'docs/memory.md',
        'docs/pipelines-and-queues.md',
        'docs/vectors-and-retrieval.md',
        'docs/streaming-and-modalities.md',
        'docs/errors-and-telemetry.md',
        'docs/testing.md',
        'docs/production.md',
        'docs/orchestration-and-blueprints.md',
        'docs/pipelines-queues-and-memory.md',
        'docs/testing-with-fakes.md',
        'docs/provider-profile-presets.md',
        'docs/github-ci-matrix.md',
        'docs/release-verification.md',
        'docs/laravel-ai-sdk-capability-matrix.md',
        'docs/sdk-async-inventory.md',
        'docs/testing-strategy.md',
        'docs/failure-normalization.md',
    ];

    $forbiddenMarkers = [
        'implementation artifact',
        'P0-I',
        'P1Y-I',
        'roadmap complete',
        'archived under openspec',
    ];

    foreach ($publicPaths as $path) {
        $contents = documentationContents($path);

        foreach ($forbiddenMarkers as $marker) {
            expect($contents)->not->toContain($marker, $path . ' should not contain internal marker [' . $marker . '].');
        }
    }
});

function documentationContents(string $relativePath): string
{
    $contents = file_get_contents(repositoryPath($relativePath));

    if (!is_string($contents)) {
        throw new RuntimeException(sprintf('Unable to read [%s].', $relativePath));
    }

    return $contents;
}

function repositoryPath(string $relativePath): string
{
    return dirname(__DIR__) . '/' . $relativePath;
}
