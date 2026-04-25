<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\SchemaResolutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredRuntimeTelemetryAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\SerializableClosure\SerializableClosure;

it('adapts an ObjectSchema instance into the structured agent closure', function (): void {
    app()->register(AiServiceProvider::class);

    $objectSchema = new ObjectSchema([]);

    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
      static fn () => new StructuredAgentResponse(
          'inv-structured',
          ['ok' => true],
          'Structured response text',
          new Usage(promptTokens: 1, completionTokens: 1),
          new Meta(provider: 'openai', model: 'gpt-4o-mini'),
      ),
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-structured-object-schema',
            prompt: 'Use a declarative ObjectSchema for the response shape.',
            provider: 'openai',
            schema: $objectSchema,
        ),
    );

    Ai::assertAgentWasPrompted(StructuredRuntimeTelemetryAgent::class, function ($prompt) use ($objectSchema): bool {
        $closure = $prompt->agent->schema;

        if (!$closure instanceof SerializableClosure) {
            return false;
        }

        $resolved = $closure(new JsonSchemaTypeFactory());

        return $resolved === $objectSchema->toSchema();
    });
});

it('resolves a class-string schema via the container into the structured agent closure', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
      static fn () => new StructuredAgentResponse(
          'inv-structured',
          ['ok' => true],
          'Structured response text',
          new Usage(promptTokens: 1, completionTokens: 1),
          new Meta(provider: 'openai', model: 'gpt-4o-mini'),
      ),
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-structured-class-string',
            prompt: 'Use a container-resolved schema definition.',
            provider: 'openai',
            schema: TestStructuredOutputSchema::class,
        ),
    );

    Ai::assertAgentWasPrompted(StructuredRuntimeTelemetryAgent::class, function ($prompt): bool {
        $closure = $prompt->agent->schema;

        if (!$closure instanceof SerializableClosure) {
            return false;
        }

        $resolved = $closure(new JsonSchemaTypeFactory());

        return $resolved === ['summary' => 'string'];
    });
});

it('raises SchemaResolutionException when the schema class-string does not implement HasStructuredOutput', function (): void {
    app()->register(AiServiceProvider::class);

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn () => $runtime->execute(
        new ExecutionRequest(
            runId: 'run-structured-class-mismatch',
            prompt: 'Try a class that does not implement the contract.',
            provider: 'openai',
            schema: TestStructuredOutputBadSchema::class,
        ),
    ))->toThrow(SchemaResolutionException::class, TestStructuredOutputBadSchema::class);
});

final class TestStructuredOutputSchema implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return ['summary' => 'string'];
    }
}

final class TestStructuredOutputBadSchema
{
}
