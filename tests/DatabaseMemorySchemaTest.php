<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('ai_agent_conversation_messages');
    Schema::dropIfExists('ai_agent_conversations');
});

it('creates the conversation and message tables with the expected schema', function (): void {
    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    /** @var Migration $createMessages */
    $createMessages = require __DIR__ . '/../database/migrations/create_ai_agent_conversation_messages_table.php.stub';

    $createConversations->up();
    $createMessages->up();

    expect(Schema::hasTable('ai_agent_conversations'))
      ->toBeTrue()
      ->and(
          Schema::hasColumns('ai_agent_conversations', [
          'id',
          'conversation_id',
          'driver',
          'store_conversation',
          'is_encrypted',
          'retention_until',
          'last_message_at',
          'summary_ciphertext',
          'metadata_ciphertext',
          'created_at',
          'updated_at',
          'deleted_at',
        ]),
      )->toBeTrue()
      ->and(Schema::hasTable('ai_agent_conversation_messages'))->toBeTrue()
      ->and(
          Schema::hasColumns('ai_agent_conversation_messages', [
          'id',
          'conversation_record_id',
          'message_id',
          'sequence',
          'role',
          'content_ciphertext',
          'metadata_ciphertext',
          'token_count',
          'created_at',
          'updated_at',
        ]),
      )->toBeTrue();

    DB::table('ai_agent_conversations')->insert([
      'conversation_id' => 'conv-001',
      'driver' => 'database',
      'store_conversation' => true,
      'is_encrypted' => true,
      'retention_until' => '2026-04-01 00:00:00',
      'last_message_at' => '2026-03-14 12:00:00',
      'summary_ciphertext' => 'encrypted-summary',
      'metadata_ciphertext' => '{"tenant":"creativecrafts"}',
      'created_at' => '2026-03-14 12:00:00',
      'updated_at' => '2026-03-14 12:00:00',
    ]);

    $conversationRecordId = (int)DB::table('ai_agent_conversations')->value('id');

    DB::table('ai_agent_conversation_messages')->insert([
      'conversation_record_id' => $conversationRecordId,
      'message_id' => 'msg-001',
      'sequence' => 1,
      'role' => 'user',
      'content_ciphertext' => 'encrypted-message',
      'metadata_ciphertext' => '{"channel":"web"}',
      'token_count' => 128,
      'created_at' => '2026-03-14 12:00:00',
      'updated_at' => '2026-03-14 12:00:00',
    ]);

    expect(DB::table('ai_agent_conversation_messages')->count())->toBe(1);

    DB::table('ai_agent_conversations')->where('id', $conversationRecordId)->delete();

    expect(DB::table('ai_agent_conversation_messages')->count())->toBe(0);
});

it('drops the message table before the conversation table on rollback', function (): void {
    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    /** @var Migration $createMessages */
    $createMessages = require __DIR__ . '/../database/migrations/create_ai_agent_conversation_messages_table.php.stub';

    $createConversations->up();
    $createMessages->up();

    expect(Schema::hasTable('ai_agent_conversations'))
      ->toBeTrue()
      ->and(Schema::hasTable('ai_agent_conversation_messages'))->toBeTrue();

    $createMessages->down();
    $createConversations->down();

    expect(Schema::hasTable('ai_agent_conversation_messages'))
      ->toBeFalse()
      ->and(Schema::hasTable('ai_agent_conversations'))->toBeFalse();
});
