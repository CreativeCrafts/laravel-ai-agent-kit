<?php

declare(strict_types=1);

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
