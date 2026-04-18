<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\AuditedProviderCapabilityMatrix;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderCapabilityMatrixEntry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

it('defines the audited assistant-era provider capability matrix', function (): void {
    $matrix = new AuditedProviderCapabilityMatrix();

    expect(array_keys($matrix->all()))
      ->toBe([
        'text_generation',
        'structured_output',
        'audio_transcription',
        'tool_capable_execution',
        'memory_aware_continuation',
        'text_to_structured_evaluation',
        'audio_to_text_to_evaluation',
      ])
      ->and($matrix->get('text_generation'))->toBeInstanceOf(ProviderCapabilityMatrixEntry::class)
      ->and($matrix->get('text_generation')->isStagedWorkflow())->toBeFalse()
      ->and($matrix->get('audio_to_text_to_evaluation')->isStagedWorkflow())->toBeTrue();
});

it('reports no missing requirements when a profile satisfies an audited capability target', function (): void {
    $matrix = new AuditedProviderCapabilityMatrix();

    $provider = new ProviderDefinition(
        name: 'openai-general',
        driver: 'openai',
        enabled: true,
        options: [],
        capabilities: [
        'text_generation',
        'structured_output',
        'tool_execution',
        'memory_continuation',
      ],
    );

    expect($matrix->missingProfileRequirements($provider, 'structured_output'))
      ->toBe([])
      ->and($matrix->missingProfileRequirements($provider, 'tool_capable_execution'))->toBe([])
      ->and($matrix->missingProfileRequirements($provider, 'memory_aware_continuation'))->toBe([])
      ->and($matrix->conformedCapabilitiesForProfile($provider))
      ->toBe([
        'text_generation',
        'structured_output',
        'tool_capable_execution',
        'memory_aware_continuation',
        'text_to_structured_evaluation',
      ]);
});

it('reports missing requirements for audited single-profile capability targets', function (): void {
    $matrix = new AuditedProviderCapabilityMatrix();

    $provider = new ProviderDefinition(
        name: 'anthropic-text',
        driver: 'anthropic',
        enabled: true,
        options: [],
        capabilities: ['text_generation'],
    );

    expect($matrix->missingProfileRequirements($provider, 'structured_output'))
      ->toBe(['structured_output'])
      ->and($matrix->missingProfileRequirements($provider, 'tool_capable_execution'))
      ->toBe(['tool_execution'])
      ->and($matrix->missingProfileRequirements($provider, 'memory_aware_continuation'))
      ->toBe(['memory_continuation']);
});

it('reports stage-specific missing requirements for staged workflow targets', function (): void {
    $matrix = new AuditedProviderCapabilityMatrix();

    $transcriptionProvider = new ProviderDefinition(
        name: 'text-only-transcription',
        driver: 'openai',
        enabled: true,
        options: [],
        capabilities: ['text_generation'],
    );

    $evaluationProvider = new ProviderDefinition(
        name: 'text-only-evaluation',
        driver: 'openai',
        enabled: true,
        options: [],
        capabilities: ['text_generation'],
    );

    expect(
        $matrix->missingStageRequirements(
            [
          'transcription' => $transcriptionProvider,
          'evaluation' => $evaluationProvider,
        ],
            'audio_to_text_to_evaluation',
        ),
    )->toBe([
      'transcription' => ['audio_transcription'],
      'evaluation' => ['structured_output'],
    ]);
});

it('treats a missing staged provider as missing all of that stage requirements', function (): void {
    $matrix = new AuditedProviderCapabilityMatrix();

    $transcriptionProvider = new ProviderDefinition(
        name: 'audio-only',
        driver: 'openai',
        enabled: true,
        options: [],
        capabilities: ['audio_transcription'],
    );

    expect(
        $matrix->missingStageRequirements(
            [
          'transcription' => $transcriptionProvider,
        ],
            'audio_to_text_to_evaluation',
        ),
    )->toBe([
      'evaluation' => ['text_generation', 'structured_output'],
    ]);
});
