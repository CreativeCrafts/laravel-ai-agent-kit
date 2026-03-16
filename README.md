# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package that delivers a structured agent-workflow toolkit built on top of the official Laravel AI SDK. It provides provider abstraction, pipeline orchestration,
queued execution, and package foundations for building AI-powered application flows safely and predictably.

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

Publish and run migrations:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
~~~

Publish the configuration file:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

Optionally, publish the views:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-views"
~~~

## Configuration

The package validates its configuration during boot by default.

At least one enabled provider must exist, `default_provider` must reference an enabled configured provider, and `failover_order` must include the default provider.

The default memory driver is now `in_memory`. That default is explicit, non-persistent, and safe for tests, local development, and ephemeral runs. Switch `memory.default_driver` to `database` when you
want encrypted persistent storage and retention-based purging.

Example configuration:

~~~php
return [
    'validation' => [
        'enabled' => true,
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],
    ],

    'default_provider' => 'null',

    'failover_order' => ['null'],

    'budgets' => [
        'max_steps' => 20,
        'max_tool_calls' => 50,
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 120,
        'max_tokens' => null,
        'max_cost_usd' => null,
    ],

    'memory' => [
        'default_driver' => 'in_memory',

        'in_memory' => [
            'retention_days' => null,
        ],

        'database' => [
            'connection' => null,
            'conversations_table' => 'ai_agent_conversations',
            'messages_table' => 'ai_agent_conversation_messages',
            'driver_name' => 'database',
            'retention_days' => 30,
            'encrypt_payloads' => true,
        ],
    ],
];
~~~

## Usage

Resolve the configured provider registry or default provider selector through the container:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;

$registry = app(ProviderRegistry::class);
$selector = app(ProviderSelector::class);

$defaultProvider = $selector->selectDefault();
$provider = $registry->get('null');
~~~

Build and run a synchronous pipeline with typed steps:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

$pipeline = PipelineBuilder::make()
    ->addStep(new class implements PipelineStep
    {
        public function handle(RunContext $context): RunContext
        {
            return $context
                ->withStateValue('normalized', true)
                ->incrementStepCount();
        }
    })
    ->build();

$runner = app(PipelineRunner::class);

$result = $runner->run(
    $pipeline,
    new RunContext(
        runId: 'run-001',
        input: ['text' => 'Hello world'],
    ),
);
~~~

Dispatch a queued pipeline using a typed pipeline definition and explicit result handler:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class NormalizeTranscriptPipeline implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();
    }
}

final class StorePipelineResult implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
        // Persist or publish the result explicitly.
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        // Persist or publish the failure explicitly.
    }
}

$dispatcher = app(QueuedPipelineDispatcher::class);

$dispatcher->dispatch(
    pipelineDefinition: NormalizeTranscriptPipeline::class,
    context: new RunContext(
        runId: 'run-queued-001',
        input: ['text' => 'Hello world'],
    ),
    options: new QueueDispatchOptions(
        connection: 'redis',
        queue: 'ai-pipelines',
        delaySeconds: 5,
        timeoutSeconds: 90,
    ),
    resultHandler: StorePipelineResult::class,
);
~~~

Use the memory contracts through their default non-persistent driver:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

$conversationStore = app(ConversationStore::class);
$retentionPurger = app(ConversationRetentionPurger::class);

$conversationStore->save(new Conversation(
    id: new ConversationId('conv-001'),
    createdAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
    updatedAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
    messages: [
        new ConversationMessage(
            id: new MessageId('msg-001'),
            role: ConversationMessageRole::User,
            content: 'Hello world',
            createdAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
        ),
    ],
));

$conversation = $conversationStore->find(new ConversationId('conv-001'));
$purgedCount = $retentionPurger->purgeExpired();
~~~

Switch to the database driver when you need persistence:

~~~php
// config/ai-agent-kit.php
'memory' => [
    'default_driver' => 'database',
    // ...
],
~~~

## Testing

~~~bash
composer test
~~~

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

The MIT Licence (MIT). Please see [Licence File](LICENSE.md) for more information.