<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Memory\NullConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Memory\SummarizationInput;
use CreativeCrafts\LaravelAiAgentKit\Memory\SummarizationResult;

it('binds the conversation summarizer contract to the default null summarizer', function () {
    config()->set('ai-agent-kit.summarization.enabled', false);
    config()->set('ai-agent-kit.summarization.trigger_message_count', 3);

    $summarizer = app(ConversationSummarizer::class);

    expect($summarizer)->toBeInstanceOf(NullConversationSummarizer::class);
});

it('triggers summarization only when enabled and the configured threshold is reached', function () {
    $summarizer = new NullConversationSummarizer(
        enabled: true,
        triggerMessageCount: 3,
    );

    $belowThreshold = new SummarizationInput(
        conversationId: new ConversationId('conv-below'),
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-1'),
            role: ConversationMessageRole::User,
            content: 'one',
            createdAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
        ),
        new ConversationMessage(
            id: new MessageId('msg-2'),
            role: ConversationMessageRole::Assistant,
            content: 'two',
            createdAt: new DateTimeImmutable('2026-03-14T09:01:00+00:00'),
        ),
      ],
    );

    $atThreshold = new SummarizationInput(
        conversationId: new ConversationId('conv-at'),
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-3'),
            role: ConversationMessageRole::User,
            content: 'one',
            createdAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
        ),
        new ConversationMessage(
            id: new MessageId('msg-4'),
            role: ConversationMessageRole::Assistant,
            content: 'two',
            createdAt: new DateTimeImmutable('2026-03-14T09:01:00+00:00'),
        ),
        new ConversationMessage(
            id: new MessageId('msg-5'),
            role: ConversationMessageRole::User,
            content: 'three',
            createdAt: new DateTimeImmutable('2026-03-14T09:02:00+00:00'),
        ),
      ],
    );

    expect($summarizer->shouldSummarize($belowThreshold))
      ->toBeFalse()
      ->and($summarizer->shouldSummarize($atThreshold))->toBeTrue();
});

it('returns a safe no-op summarization result that does not request persistence', function () {
    $summarizer = new NullConversationSummarizer(
        enabled: true,
        triggerMessageCount: 2,
    );

    $input = new SummarizationInput(
        conversationId: new ConversationId('conv-summary'),
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-6'),
            role: ConversationMessageRole::User,
            content: 'one',
            createdAt: new DateTimeImmutable('2026-03-14T09:00:00+00:00'),
        ),
        new ConversationMessage(
            id: new MessageId('msg-7'),
            role: ConversationMessageRole::Assistant,
            content: 'two',
            createdAt: new DateTimeImmutable('2026-03-14T09:01:00+00:00'),
        ),
      ],
        existingSummary: 'existing summary',
        metadata: ['source' => 'test'],
    );

    $result = $summarizer->summarize($input);

    expect($result)
      ->toBeInstanceOf(SummarizationResult::class)
      ->and($result->summary)->toBe('existing summary')
      ->and($result->shouldPersist)->toBeFalse()
      ->and($result->summarizedMessageCount)->toBe(2)
      ->and($result->metadataValue('driver'))->toBe('null')
      ->and($result->hasSummary())->toBeTrue();
});

it('validates summarization config shape and threshold values', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
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
        'max_steps' => 1,
        'max_tool_calls' => 1,
        'max_retries_per_step' => 1,
        'max_total_timeout_seconds' => 1,
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
      'summarization' => [
        'enabled' => true,
        'trigger_message_count' => 0,
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'summarization.trigger_message_count');
