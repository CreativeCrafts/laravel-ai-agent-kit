<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeProviderPolicy;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeToolRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Tests\TestCase;
use PHPUnit\Framework\Assert;

uses(TestCase::class)->in(__DIR__);

expect()->extend('toHaveRuntimeExecutions', function (int $expectedCount) {
    if (!$this->value instanceof FakeAiRuntime) {
        Assert::fail('toHaveRuntimeExecutions() expects a FakeAiRuntime instance.');
    }

    PackageAssertions::assertRuntimeExecutedTimes($this->value, $expectedCount);

    return $this;
});

expect()->extend('toHaveSelectedDefaultProvider', function (string $providerName) {
    if (!$this->value instanceof FakeProviderPolicy) {
        Assert::fail('toHaveSelectedDefaultProvider() expects a FakeProviderPolicy instance.');
    }

    PackageAssertions::assertDefaultProviderSelected($this->value, $providerName);

    return $this;
});

expect()->extend('toHaveExecutedTool', function (string $toolName, ?array $expectedInput = null) {
    if (!$this->value instanceof FakeToolRunner) {
        Assert::fail('toHaveExecutedTool() expects a FakeToolRunner instance.');
    }

    PackageAssertions::assertToolExecuted($this->value, $toolName, $expectedInput);

    return $this;
});

expect()->extend('toContainConversation', function (string $conversationId) {
    if (!$this->value instanceof FakeConversationStore) {
        Assert::fail('toContainConversation() expects a FakeConversationStore instance.');
    }

    PackageAssertions::assertConversationExists($this->value, $conversationId);

    return $this;
});

expect()->extend('toMissConversation', function (string $conversationId) {
    if (!$this->value instanceof FakeConversationStore) {
        Assert::fail('toMissConversation() expects a FakeConversationStore instance.');
    }

    PackageAssertions::assertConversationMissing($this->value, $conversationId);

    return $this;
});

expect()->extend('toHaveLastPurgeCount', function (int $expectedCount) {
    if (!$this->value instanceof FakeConversationStore) {
        Assert::fail('toHaveLastPurgeCount() expects a FakeConversationStore instance.');
    }

    PackageAssertions::assertLastPurgeCount($this->value, $expectedCount);

    return $this;
});

expect()->extend('toStoreVectorDocument', function (string $namespace, string $documentId) {
    if (!$this->value instanceof FakeVectorStore) {
        Assert::fail('toStoreVectorDocument() expects a FakeVectorStore instance.');
    }

    PackageAssertions::assertVectorDocumentStored($this->value, $namespace, $documentId);

    return $this;
});

expect()->extend('toHaveRecordedVectorDeletion', function (string $namespace, array $documentIds) {
    if (!$this->value instanceof FakeVectorStore) {
        Assert::fail('toHaveRecordedVectorDeletion() expects a FakeVectorStore instance.');
    }

    PackageAssertions::assertVectorDeletionRecorded($this->value, $namespace, $documentIds);

    return $this;
});

expect()->extend('toHaveOrchestrationExecutions', function (int $expectedCount) {
    if (!$this->value instanceof FakeAgentOrchestrator) {
        Assert::fail('toHaveOrchestrationExecutions() expects a FakeAgentOrchestrator instance.');
    }

    PackageAssertions::assertOrchestrationExecutedTimes($this->value, $expectedCount);

    return $this;
});

expect()->extend('toBeCompletedOrchestration', function (?string $finalAgent = null) {
    if (!$this->value instanceof OrchestrationResult) {
        Assert::fail('toBeCompletedOrchestration() expects an OrchestrationResult instance.');
    }

    PackageAssertions::assertOrchestrationCompleted($this->value, $finalAgent);

    return $this;
});

expect()->extend('toHaveExecutionTree', function (array $expectedTree) {
    if (!$this->value instanceof OrchestrationResult) {
        Assert::fail('toHaveExecutionTree() expects an OrchestrationResult instance.');
    }

    PackageAssertions::assertExecutionTree($this->value, $expectedTree);

    return $this;
});

expect()->extend('toHaveDelegatedTo', function (string $sourceAgent, string $targetAgent) {
    if (!$this->value instanceof OrchestrationResult) {
        Assert::fail('toHaveDelegatedTo() expects an OrchestrationResult instance.');
    }

    PackageAssertions::assertDelegationOccurred($this->value, $sourceAgent, $targetAgent);

    return $this;
});

expect()->extend('toHaveTransferredControlTo', function (string $targetAgent) {
    if (!$this->value instanceof OrchestrationResult) {
        Assert::fail('toHaveTransferredControlTo() expects an OrchestrationResult instance.');
    }

    PackageAssertions::assertOwnershipTransferred($this->value, $targetAgent);

    return $this;
});

expect()->extend('toHaveHandoffSummary', function (string $sourceAgent, string $expectedSummary) {
    if (!$this->value instanceof OrchestrationResult) {
        Assert::fail('toHaveHandoffSummary() expects an OrchestrationResult instance.');
    }

    PackageAssertions::assertHandoffSummary($this->value, $sourceAgent, $expectedSummary);

    return $this;
});
