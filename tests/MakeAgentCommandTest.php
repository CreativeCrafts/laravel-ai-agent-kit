<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectContext;
use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;
use Illuminate\Console\Command as ConsoleCommand;

it('generates an agent scaffold with the expected normalized path namespace and prompt blueprint stub', function () {
    $context = makeAgentCommandTestContext();
    $generatedPath = makeAgentCommandTestPath('Support\\ReviewAgent');

    makeAgentCommandTestCleanup($generatedPath);

    $this->artisan('ai:make:agent', [
      'name' => 'support/review-agent',
    ])->assertSuccessful();

    expect(is_file($generatedPath))->toBeTrue();

    $contents = file_get_contents($generatedPath);
    $baseNamespace = $context->agentsNamespace();

    expect($baseNamespace)->not
      ->toBeNull()
      ->and($contents)->not
      ->toBeFalse()
      ->and($contents)->toContain(sprintf('namespace %s\\Support;', $baseNamespace))
      ->and($contents)->toContain('final class ReviewAgent implements Agent')
      ->and($contents)->toContain('function definition(): AgentDefinition')
      ->and($contents)->toContain('function handle(AgentExecutionContext $context): AgentExecutionResult')
      ->and($contents)->toContain("key: 'support.review'")
      ->and($contents)->toContain('public function blueprint(array $variables = []): PromptBlueprint')
      ->and($contents)->toContain("return LaravelAiAgentKit::prompt('support.review')");

    makeAgentCommandTestAssertCompiles($generatedPath);

    makeAgentCommandTestCleanup($generatedPath);
});

it('does not overwrite an existing generated agent file without the force option', function () {
    $generatedPath = makeAgentCommandTestPath('ExistingAgent');

    makeAgentCommandTestCleanup($generatedPath);
    makeAgentCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'original-agent');

    $this->artisan('ai:make:agent', [
      'name' => 'ExistingAgent',
    ])->assertExitCode(ConsoleCommand::FAILURE);

    expect(file_get_contents($generatedPath))->toBe('original-agent');

    makeAgentCommandTestCleanup($generatedPath);
});

it('overwrites an existing generated agent file when the force option is supplied', function () {
    $context = makeAgentCommandTestContext();
    $generatedPath = makeAgentCommandTestPath('ForcedAgent');

    makeAgentCommandTestCleanup($generatedPath);
    makeAgentCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'stale-agent');

    $this->artisan('ai:make:agent', [
      'name' => 'ForcedAgent',
      '--force' => true,
    ])->assertSuccessful();

    $contents = file_get_contents($generatedPath);
    $baseNamespace = $context->agentsNamespace();

    expect($baseNamespace)->not
      ->toBeNull()
      ->and($contents)->not
      ->toBeFalse()
      ->and($contents)->not
      ->toBe('stale-agent')
      ->and($contents)->toContain(sprintf('namespace %s;', $baseNamespace))
      ->and($contents)->toContain('final class ForcedAgent implements Agent')
      ->and($contents)->toContain("key: 'forced'")
      ->and($contents)->toContain("return LaravelAiAgentKit::prompt('forced')");

    makeAgentCommandTestAssertCompiles($generatedPath);

    makeAgentCommandTestCleanup($generatedPath);
});

function makeAgentCommandTestContext(): ProjectContext
{
    return (new ProjectInspector(base_path()))->inspect();
}

/**
 * @param non-empty-string $relativeClass
 */
function makeAgentCommandTestPath(string $relativeClass): string
{
    return makeAgentCommandTestContext()->agentsDirectory() . '/' . str_replace('\\', '/', $relativeClass) . '.php';
}

function makeAgentCommandTestEnsureDirectoryExists(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0755, true);
}

function makeAgentCommandTestCleanup(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }

    $directory = dirname($path);
    $root = makeAgentCommandTestContext()->agentsDirectory();

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

function makeAgentCommandTestAssertCompiles(string $path): void
{
    $command = sprintf(
        '%s -l %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($path),
    );

    $output = [];
    $exitCode = 1;

    exec($command, $output, $exitCode);

    expect($exitCode)
      ->toBe(0)
      ->and(implode("\n", $output))->toContain('No syntax errors detected');
}
