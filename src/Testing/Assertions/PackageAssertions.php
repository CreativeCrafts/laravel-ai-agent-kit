<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Assertions;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeProviderPolicy;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeToolRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use PHPUnit\Framework\Assert;

final class PackageAssertions
{
    public static function assertRuntimeExecutedTimes(FakeAiRuntime $fake, int $expectedCount): void
    {
        Assert::assertCount(
            $expectedCount,
            $fake->requests(),
            sprintf('Expected fake runtime to execute %d time(s).', $expectedCount),
        );
    }

    /**
     * @param callable(ExecutionRequest): void $assertion
     */
    public static function assertLastRuntimeRequest(FakeAiRuntime $fake, callable $assertion): void
    {
        $request = $fake->lastRequest();

        Assert::assertNotNull($request, 'Expected fake runtime to have a last execution request.');

        $assertion($request);
    }

    public static function assertDefaultProviderSelected(FakeProviderPolicy $fake, string $providerName): void
    {
        Assert::assertContains(
            $providerName,
            $fake->selectedDefaults(),
            sprintf('Expected fake provider policy to select [%s] as a default provider.', $providerName),
        );
    }

    public static function assertProviderRequested(FakeProviderPolicy $fake, string $providerName): void
    {
        Assert::assertContains(
            $providerName,
            $fake->requestedProviders(),
            sprintf('Expected fake provider policy to request provider [%s].', $providerName),
        );
    }

    public static function assertFailoverLookedUp(FakeProviderPolicy $fake, string $providerName): void
    {
        Assert::assertContains(
            $providerName,
            $fake->failoverLookups(),
            sprintf('Expected fake provider policy to perform a failover lookup from [%s].', $providerName),
        );
    }

    /**
     * @param array<string, mixed>|null $expectedInput
     */
    public static function assertToolExecuted(FakeToolRunner $fake, string $toolName, ?array $expectedInput = null): void
    {
        $execution = $fake->lastExecution();

        Assert::assertNotNull($execution, 'Expected fake tool runner to have at least one execution recorded.');
        Assert::assertSame($toolName, $execution['name'], sprintf('Expected fake tool runner to execute tool [%s].', $toolName));

        if ($expectedInput !== null) {
            Assert::assertSame(
                $expectedInput,
                $execution['input'],
                sprintf('Expected fake tool runner execution input to match for tool [%s].', $toolName),
            );
        }
    }

    public static function assertConversationExists(FakeConversationStore $fake, string $conversationId): Conversation
    {
        $conversation = $fake->find(new ConversationId($conversationId));

        Assert::assertNotNull(
            $conversation,
            sprintf('Expected fake conversation store to contain conversation [%s].', $conversationId),
        );

        return $conversation;
    }

    public static function assertConversationMissing(FakeConversationStore $fake, string $conversationId): void
    {
        Assert::assertNull(
            $fake->find(new ConversationId($conversationId)),
            sprintf('Expected fake conversation store to be missing conversation [%s].', $conversationId),
        );
    }

    public static function assertLastPurgeCount(FakeConversationStore $fake, int $expectedCount): void
    {
        $purgeCounts = $fake->purgeCounts();

        Assert::assertNotEmpty($purgeCounts, 'Expected fake conversation store to record at least one purge operation.');

        $lastPurgeCount = $purgeCounts[array_key_last($purgeCounts)];

        Assert::assertSame(
            $expectedCount,
            $lastPurgeCount,
            sprintf('Expected fake conversation store last purge count to be %d.', $expectedCount),
        );
    }

    public static function assertVectorDocumentStored(FakeVectorStore $fake, string $namespace, string $documentId): VectorDocument
    {
        $documents = $fake->documents($namespace);

        Assert::assertArrayHasKey(
            $documentId,
            $documents,
            sprintf('Expected fake vector store namespace [%s] to contain document [%s].', $namespace, $documentId),
        );

        return $documents[$documentId];
    }

    public static function assertVectorSearchRecordedTimes(FakeVectorStore $fake, int $expectedCount): void
    {
        Assert::assertCount(
            $expectedCount,
            $fake->searches(),
            sprintf('Expected fake vector store to record %d search operation(s).', $expectedCount),
        );
    }

    /**
     * @param list<string> $documentIds
     */
    public static function assertVectorDeletionRecorded(FakeVectorStore $fake, string $namespace, array $documentIds): void
    {
        $deletions = $fake->deletions();

        Assert::assertNotEmpty($deletions, 'Expected fake vector store to record at least one deletion.');

        $lastDeletion = $deletions[array_key_last($deletions)];

        Assert::assertSame(
            [
            'namespace' => $namespace,
            'document_ids' => $documentIds,
          ],
            $lastDeletion,
            sprintf('Expected fake vector store to record a deletion for namespace [%s].', $namespace),
        );
    }
}
