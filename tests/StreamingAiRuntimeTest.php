<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\MiddlewareExecutingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamChunk;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamChunkEmitted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeStreamFailed;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Runtime\TestRuntimeMiddlewareA;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;

it('streams ordered text chunks then a terminal complete event', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['One two three four'])->preventStrayPrompts();

    /** @var StreamingAiRuntime $streaming */
    $streaming = app(StreamingAiRuntime::class);

    $events = iterator_to_array($streaming->executeStream(
        new ExecutionRequest(
            runId: 'run-stream-001',
            prompt: 'Count to four.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    ));

    $chunks = array_values(array_filter($events, static fn ($e): bool => $e instanceof StreamChunk));
    $terminal = array_values(array_filter($events, static fn ($e): bool => $e instanceof StreamComplete));

    expect($chunks)->not->toBeEmpty()
        ->and($terminal)->toHaveCount(1)
        ->and($terminal[0])->toBeInstanceOf(StreamComplete::class)
        ->and($terminal[0]->output)->toBe('One two three four');

    $sequences = array_map(static fn (StreamChunk $c): int => $c->sequence, $chunks);
    expect($sequences)->toBe(range(0, count($sequences) - 1));
});

it('yields a single terminal failure and dispatches telemetry when the sdk stream fails', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static fn (): never => throw new RuntimeException('provider stream failed'),
    ])->preventStrayPrompts();

    Event::fake([
        RuntimeStreamChunkEmitted::class,
        RuntimeStreamCompleted::class,
        RuntimeStreamFailed::class,
    ]);

    /** @var StreamingAiRuntime $streaming */
    $streaming = app(StreamingAiRuntime::class);

    $events = iterator_to_array($streaming->executeStream(
        new ExecutionRequest(
            runId: 'run-stream-fail-001',
            prompt: 'Trigger failure.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    ));

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(StreamFailure::class)
        ->and($events[0]->failureCategory)->toBe('provider_failure')
        ->and($events[0]->exceptionMessage)->toBe('provider stream failed');

    Event::assertDispatched(RuntimeStreamFailed::class, function (RuntimeStreamFailed $event): bool {
        return $event->runId === 'run-stream-fail-001'
            && $event->failureCategory === 'provider_failure'
            && $event->exceptionMessage === 'provider stream failed';
    });
});

it('rejects streaming when execution request carries a schema', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ignored'])->preventStrayPrompts();

    /** @var StreamingAiRuntime $streaming */
    $streaming = app(StreamingAiRuntime::class);

    expect(fn () => iterator_to_array($streaming->executeStream(
        new ExecutionRequest(
            runId: 'run-stream-schema-001',
            prompt: 'Structured.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            schema: static fn ($js): array => [],
        ),
    )))->toThrow(InvalidArgumentException::class);
});

it('dispatches redacted stream observability events and optional broadcast when channel is set', function (): void {
    app()->register(AiServiceProvider::class);

    Config::set('ai-agent-kit.runtime.streaming.broadcast_channel', 'agent-kit-streams');

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['hello world'])->preventStrayPrompts();

    Event::fake([
        RuntimeStreamChunkEmitted::class,
        RuntimeStreamCompleted::class,
        RuntimeStreamFailed::class,
    ]);

    /** @var StreamingAiRuntime $streaming */
    $streaming = app(StreamingAiRuntime::class);

    iterator_to_array($streaming->executeStream(
        new ExecutionRequest(
            runId: 'run-stream-broadcast-001',
            prompt: 'Say hello.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    ));

    Event::assertDispatched(RuntimeStreamChunkEmitted::class, function (RuntimeStreamChunkEmitted $e): bool {
        return $e->broadcastWhen() === true
            && $e->runId === 'run-stream-broadcast-001'
            && $e->deltaLength > 0;
    });

    Event::assertDispatched(RuntimeStreamCompleted::class, function (RuntimeStreamCompleted $e): bool {
        return $e->broadcastWhen() === true
            && $e->runId === 'run-stream-broadcast-001'
            && $e->outputLength === strlen('hello world');
    });
});

it('resolves streaming through middleware wrapping the sdk runtime', function (): void {
    app()->register(AiServiceProvider::class);

    Config::set('ai-agent-kit.runtime.middleware', [TestRuntimeMiddlewareA::class]);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['wrapped stream ok'])->preventStrayPrompts();

    $runtime = app(AiRuntime::class);
    $streaming = app(StreamingAiRuntime::class);

    expect($runtime)->toBeInstanceOf(MiddlewareExecutingAiRuntime::class)
        ->and($streaming)->toBeInstanceOf(MiddlewareExecutingAiRuntime::class);

    $events = iterator_to_array($streaming->executeStream(
        new ExecutionRequest(
            runId: 'run-stream-mw-001',
            prompt: 'Go.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    ));

    $last = $events[array_key_last($events)];
    expect($last)->toBeInstanceOf(StreamComplete::class)
        ->and($last->output)->toBe('wrapped stream ok');
});
