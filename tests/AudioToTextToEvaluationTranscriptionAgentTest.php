<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationTranscriptionAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;

it('passes the rendered transcription prompt into the transcription runtime request', function (): void {
    $promptRepository = new InMemoryPromptRepository([
        'audio-to-text-to-evaluation.transcription' => [
            '1.0.0' => 'Transcribe {{ subject }} verbatim from {{ audio_mime_type }} and preserve pauses.',
        ],
    ]);
    $transcriptionRuntime = new RecordingTranscriptionRuntimeForPromptTest('rendered transcript');
    $agent = new AudioToTextToEvaluationTranscriptionAgent(
        providerRegistry: new PromptTestProviderRegistry(),
        promptRepository: $promptRepository,
        promptExecutionMapper: new PromptExecutionMapper($promptRepository),
        aiRuntime: new PromptTestAiRuntime(),
        transcriptionRuntime: $transcriptionRuntime,
    );

    $context = new AgentExecutionContext(
        orchestrationId: 'orch-transcription-prompt-001',
        executionId: 'exec-transcription-prompt-001',
        parentExecutionId: null,
        agent: new AgentDefinition(
            key: AudioToTextToEvaluationTranscriptionAgent::KEY,
            displayName: 'Transcription Agent',
            requiredCapabilities: ['audio_transcription'],
            primaryProviderProfile: 'openai',
        ),
        providerProfile: 'openai',
        task: 'Transcribe audio.',
        payload: [
            'subject' => 'support call',
            'audio_reference' => base64_encode('fake-audio'),
            'audio_mime_type' => 'audio/mpeg',
            'transcription_prompt_name' => 'audio-to-text-to-evaluation.transcription',
            'transcription_prompt_version' => '1.0.0',
            'transcription_prompt_variables' => [],
            'store_conversation' => false,
            'continue_conversation' => false,
            'conversation_id' => null,
            'transcription_model' => 'gpt-4o-transcribe',
        ],
    );

    $result = $agent->handle($context);

    expect($result->output['transcript'] ?? null)->toBe('rendered transcript')
        ->and($transcriptionRuntime->requests)->toHaveCount(1)
        ->and($transcriptionRuntime->requests[0]->prompt)->toBe('Transcribe support call verbatim from audio/mpeg and preserve pauses.')
        ->and($transcriptionRuntime->requests[0]->metadata['transcription_prompt_name'] ?? null)->toBe('audio-to-text-to-evaluation.transcription')
        ->and($transcriptionRuntime->requests[0]->metadata['transcription_prompt_version'] ?? null)->toBe('1.0.0');
});

final class RecordingTranscriptionRuntimeForPromptTest implements TranscriptionRuntime
{
    /** @var list<TranscriptionRequest> */
    public array $requests = [];

    public function __construct(private readonly string $transcript)
    {
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        $this->requests[] = $request;

        return new TranscriptionResult(
            runId: $request->runId,
            transcript: $this->transcript,
            provider: $request->provider ?? 'openai',
            model: $request->model ?? 'gpt-4o-transcribe',
            promptTokens: 0,
            completionTokens: 0,
            metadata: $request->metadata,
        );
    }
}

final class PromptTestProviderRegistry implements ProviderRegistry
{
    /** @return array<string, ProviderDefinition> */
    public function all(): array
    {
        return [
            'openai' => new ProviderDefinition(
                name: 'openai',
                driver: 'openai',
                enabled: true,
                capabilities: ['audio_transcription'],
            ),
        ];
    }

    public function has(string $providerName): bool
    {
        return $providerName === 'openai';
    }

    public function get(string $providerName): ProviderDefinition
    {
        return $this->all()[$providerName];
    }
}

final class PromptTestAiRuntime implements AiRuntime
{
    public function execute(ExecutionRequest $request): ExecutionResult
    {
        throw new RuntimeException('AI runtime fallback should not be called when transcription runtime succeeds.');
    }
}
