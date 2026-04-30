<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\TerminatingRuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\MiddlewareExecutingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Illuminate\Config\Repository;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;

it('invokes middleware in configured order and terminates in reverse order', function (): void {
    $log = [];

    $alpha = new class ($log) implements TerminatingRuntimeMiddleware {
        public function __construct(private array &$log)
        {
        }

        public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
        {
            $this->log[] = 'alpha-before';

            return $next($request);
        }

        public function terminate(ExecutionRequest $request, ExecutionResult $result): void
        {
            $this->log[] = 'alpha-terminate';
        }
    };

    $beta = new class ($log) implements TerminatingRuntimeMiddleware {
        public function __construct(private array &$log)
        {
        }

        public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
        {
            $this->log[] = 'beta-before';

            return $next($request);
        }

        public function terminate(ExecutionRequest $request, ExecutionResult $result): void
        {
            $this->log[] = 'beta-terminate';
        }
    };

    $fake = new FakeAiRuntime([
        static function (ExecutionRequest $request) use (&$log): ExecutionResult {
            $log[] = 'inner';

            return new ExecutionResult(runId: $request->runId, output: 'ok');
        },
    ]);

    $runtime = new MiddlewareExecutingAiRuntime($fake, [$alpha, $beta]);

    $runtime->execute(new ExecutionRequest(runId: 'mw-1', prompt: 'hi'));

    expect($log)->toBe([
        'alpha-before',
        'beta-before',
        'inner',
        'beta-terminate',
        'alpha-terminate',
    ]);
});

it('propagates exceptions from the inner runtime without swallowing', function (): void {
    $throwing = new FakeAiRuntime([
        new RuntimeException('inner boom'),
    ]);

    $observed = new class () implements RuntimeMiddleware {
        public bool $afterNext = false;

        public function handle(ExecutionRequest $request, Closure $next): ExecutionResult
        {
            try {
                return $next($request);
            } finally {
                $this->afterNext = true;
            }
        }
    };

    $runtime = new MiddlewareExecutingAiRuntime($throwing, [$observed]);

    expect(fn () => $runtime->execute(new ExecutionRequest(runId: 'mw-err', prompt: 'x')))
        ->toThrow(RuntimeException::class, 'inner boom')
        ->and($observed->afterNext)->toBeTrue();
});

it('rejects runtime middleware config entries that are not RuntimeMiddleware classes', function (): void {
    $base = require __DIR__ . '/../config/ai-agent-kit.php';
    $base['runtime'] = [
        'middleware' => [stdClass::class],
    ];

    $validator = new ConfigValidator(new Repository(['ai-agent-kit' => $base]));

    expect(fn () => $validator->validateCurrentConfig())
        ->toThrow(InvalidConfigurationException::class);
});
