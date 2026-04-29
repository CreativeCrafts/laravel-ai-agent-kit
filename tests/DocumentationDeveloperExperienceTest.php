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

it('teaches injection-first workflow usage in the readme', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('### Injection-first workflow usage')
      ->toContain('TextToStructuredEvaluation $evaluation')
      ->toContain('public function __invoke(Request $request, TextToStructuredEvaluation $evaluation): JsonResponse')
      ->toContain('### AgentKit facade shortcuts')
      ->toContain('AgentKit::evaluateText(')
      ->toContain('AgentKit::evaluateAudio(')
      ->toContain('AgentKit::orchestrate(');
});

it('documents that the default vector store binding is in-memory', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('in-memory')
      ->toContain('VectorStoreInterface');
});

it('uses dependency injection for the remaining infrastructure contract examples in the readme', function (): void {
    $contents = documentationContents('README.md');

    expect($contents)
      ->toContain('private PipelineRunner $runner')
      ->toContain('private QueuedPipelineDispatcher $dispatcher')
      ->toContain('private ConversationStore $store')
      ->toContain('private ConversationContextManager $contextManager')
      ->toContain('private VectorStoreInterface $vectorStore')
      ->not->toContain('app(PipelineRunner::class)')
      ->not->toContain('app(QueuedPipelineDispatcher::class)')
      ->not->toContain('app(ConversationStore::class)')
      ->not->toContain('app(ConversationContextManager::class)')
      ->not->toContain('app(VectorStoreInterface::class)');
});

it('aligns the provider preset documentation with the new workflow dx guidance', function (): void {
    $contents = documentationContents('docs/provider-profile-presets.md');

    expect($contents)
      ->toContain('prefer constructor or method injection for top-level workflows')
      ->toContain('AgentKit::evaluateText(')
      ->toContain('AgentKit::evaluateAudio(')
      ->toContain('AgentKit::orchestrate(')
      ->not->toContain('app(TextToStructuredEvaluation::class)->evaluate(')
      ->not->toContain('app(AudioToTextToEvaluation::class)->evaluate(')
      ->not->toContain('app(AgentOrchestrator::class)->run(');
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
