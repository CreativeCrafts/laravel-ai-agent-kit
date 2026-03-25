<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectContext;
use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;
use Illuminate\Console\Command as ConsoleCommand;

it('generates a pipeline scaffold with the expected normalized path namespace and queued pipeline stub', function () {
    $context = makePipelineCommandTestContext();
    $generatedPath = makePipelineCommandTestPath('Support\\ReviewPipeline');

    makePipelineCommandTestCleanup($generatedPath);

    $this->artisan('ai:make:pipeline', [
      'name' => 'support/review-pipeline',
    ])->assertSuccessful();

    expect(is_file($generatedPath))->toBeTrue();

    $contents = file_get_contents($generatedPath);
    $baseNamespace = $context->pipelinesNamespace();

    expect($baseNamespace)->not->toBeNull();

    expect($contents)->not
      ->toBeFalse()
      ->and($contents)->toContain(sprintf('namespace %s\\Support;', $baseNamespace))
      ->and($contents)->toContain('final class ReviewPipeline implements QueuedPipelineDefinition')
      ->and($contents)->toContain('public function build(): Pipeline')
      ->and($contents)->toContain('return PipelineBuilder::make()')
      ->and($contents)->toContain('// ->addStep(new FirstPipelineStep())');

    makePipelineCommandTestCleanup($generatedPath);
});

it('does not overwrite an existing generated pipeline file without the force option', function () {
    $generatedPath = makePipelineCommandTestPath('ExistingPipeline');

    makePipelineCommandTestCleanup($generatedPath);
    makePipelineCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'original-pipeline');

    $this->artisan('ai:make:pipeline', [
      'name' => 'ExistingPipeline',
    ])->assertExitCode(ConsoleCommand::FAILURE);

    expect(file_get_contents($generatedPath))->toBe('original-pipeline');

    makePipelineCommandTestCleanup($generatedPath);
});

it('overwrites an existing generated pipeline file when the force option is supplied', function () {
    $context = makePipelineCommandTestContext();
    $generatedPath = makePipelineCommandTestPath('ForcedPipeline');

    makePipelineCommandTestCleanup($generatedPath);
    makePipelineCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'stale-pipeline');

    $this->artisan('ai:make:pipeline', [
      'name' => 'ForcedPipeline',
      '--force' => true,
    ])->assertSuccessful();

    $contents = file_get_contents($generatedPath);
    $baseNamespace = $context->pipelinesNamespace();

    expect($baseNamespace)->not->toBeNull();

    expect($contents)->not
      ->toBeFalse()
      ->and($contents)->not
      ->toBe('stale-pipeline')
      ->and($contents)->toContain(sprintf('namespace %s;', $baseNamespace))
      ->and($contents)->toContain('final class ForcedPipeline implements QueuedPipelineDefinition')
      ->and($contents)->toContain('return PipelineBuilder::make()');

    makePipelineCommandTestCleanup($generatedPath);
});

function makePipelineCommandTestContext(): ProjectContext
{
    return (new ProjectInspector(base_path()))->inspect();
}

/**
 * @param non-empty-string $relativeClass
 */
function makePipelineCommandTestPath(string $relativeClass): string
{
    return makePipelineCommandTestContext()->pipelinesDirectory() . '/' . str_replace('\\', '/', $relativeClass) . '.php';
}

function makePipelineCommandTestEnsureDirectoryExists(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0755, true);
}

function makePipelineCommandTestCleanup(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }

    $directory = dirname($path);
    $root = makePipelineCommandTestContext()->pipelinesDirectory();

    while ($directory !== $root && str_starts_with($directory, $root)) {
        if (!is_dir($directory)) {
            $directory = dirname($directory);

            continue;
        }

        $entries = scandir($directory);

        if ($entries === false || $entries === ['.', '..']) {
            rmdir($directory);
            $directory = dirname($directory);

            continue;
        }

        break;
    }
}
