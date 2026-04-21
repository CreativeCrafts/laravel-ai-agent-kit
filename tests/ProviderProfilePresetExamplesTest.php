<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationTranscriptionAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\AuditedProviderCapabilityMatrix;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;

it('ships a gemini structured evaluation preset that validates and selects the tested structured profile', function (): void {
    $preset = applyProviderProfilePreset('gemini_structured_evaluation');

    app(ConfigValidator::class)->validate($preset);

    $matrix = new AuditedProviderCapabilityMatrix();
    $provider = app(ProviderRegistry::class)->get('gemini-structured');
    $definition = app(TextToStructuredEvaluationSpecialistAgent::class)->definition();

    expect($matrix->conformedCapabilitiesForProfile($provider))
      ->toContain('structured_output')
      ->toContain('text_to_structured_evaluation')
      ->and($definition->primaryProviderProfile)->toBe('gemini-structured')
      ->and($definition->fallbackProviderProfiles)->toBe([]);
});

it('ships a mixed xai to gemini audio preset that validates and selects the tested staged profiles', function (): void {
    $preset = applyProviderProfilePreset('xai_to_gemini_audio_review');

    app(ConfigValidator::class)->validate($preset);

    $matrix = new AuditedProviderCapabilityMatrix();
    $providerRegistry = app(ProviderRegistry::class);

    $missingRequirements = $matrix->missingStageRequirements(
        [
        'transcription' => $providerRegistry->get('xai-transcription'),
        'evaluation' => $providerRegistry->get('gemini-structured'),
      ],
        'audio_to_text_to_evaluation',
    );

    $transcriptionDefinition = app(AudioToTextToEvaluationTranscriptionAgent::class)->definition();
    $evaluationDefinition = app(TextToStructuredEvaluationSpecialistAgent::class)->definition();

    expect($missingRequirements)
      ->toBe([])
      ->and($transcriptionDefinition->primaryProviderProfile)->toBe('xai-transcription')
      ->and($transcriptionDefinition->fallbackProviderProfiles)->toBe([])
      ->and($evaluationDefinition->primaryProviderProfile)->toBe('gemini-structured')
      ->and($evaluationDefinition->fallbackProviderProfiles)->toBe([]);
});

it('ships an xai orchestrator text-generation preset without overclaiming structured-output support', function (): void {
    $preset = applyProviderProfilePreset('xai_orchestrator_text_generation');

    app(ConfigValidator::class)->validate($preset);

    $matrix = new AuditedProviderCapabilityMatrix();
    $provider = app(ProviderRegistry::class)->get('xai-general');
    $defaultProvider = app(ProviderSelector::class)->selectDefault();

    expect($matrix->conformedCapabilitiesForProfile($provider))
      ->toContain('text_generation')
      ->not->toContain('structured_output')
      ->not
      ->toContain('text_to_structured_evaluation')
      ->and($defaultProvider->name)->toBe('xai-general');
});

/**
 * @return array<string, array{
 *   providers: array<string, array{
 *     driver: string,
 *     enabled: bool,
 *     capabilities: list<string>,
 *     options: array<string, mixed>
 *   }>,
 *   default_provider: string,
 *   failover_order: list<string>
 * }>
 */
function providerProfilePresets(): array
{
    $presets = require __DIR__ . '/../examples/provider-profile-presets.php';

    if (!is_array($presets)) {
        throw new RuntimeException('examples/provider-profile-presets.php must return an array.');
    }

    return $presets;
}

/**
 * @return array{
 *   providers: array<string, array{
 *     driver: string,
 *     enabled: bool,
 *     capabilities: list<string>,
 *     options: array<string, mixed>
 *   }>,
 *   default_provider: string,
 *   failover_order: list<string>
 * }
 */
function applyProviderProfilePreset(string $presetName): array
{
    $presets = providerProfilePresets();
    $preset = $presets[$presetName] ?? null;

    if (!is_array($preset)) {
        throw new RuntimeException(sprintf('Unknown provider profile preset [%s].', $presetName));
    }

    $providers = $preset['providers'] ?? null;
    $defaultProvider = $preset['default_provider'] ?? null;
    $failoverOrder = $preset['failover_order'] ?? null;

    if (!is_array($providers) || !is_string($defaultProvider) || !is_array($failoverOrder)) {
        throw new RuntimeException(sprintf('Provider profile preset [%s] has an invalid shape.', $presetName));
    }

    config()->set('ai-agent-kit.providers', $providers);
    config()->set('ai-agent-kit.default_provider', $defaultProvider);
    config()->set('ai-agent-kit.failover_order', $failoverOrder);

    refreshProviderProfilePresetBindings();

    return [
      'providers' => $providers,
      'default_provider' => $defaultProvider,
      'failover_order' => array_values($failoverOrder),
    ];
}

function refreshProviderProfilePresetBindings(): void
{
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredAgentProviderProfileSelector::class);
    app()->forgetInstance(AgentProviderProfileSelector::class);
}
