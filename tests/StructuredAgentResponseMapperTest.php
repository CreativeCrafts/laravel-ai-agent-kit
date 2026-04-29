<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredAgentResponseMapper;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;

it('maps structured agent response payload to an associative array', function (): void {
    $response = new StructuredAgentResponse(
        'inv-map-001',
        ['score' => 9, 'label' => 'ok'],
        'Human-readable text',
        new Usage(promptTokens: 1, completionTokens: 2),
        new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );

    expect(StructuredAgentResponseMapper::mapStructuredPayload($response))
        ->toBe(['score' => 9, 'label' => 'ok']);
});

it('returns null for plain agent responses', function (): void {
    $response = new AgentResponse(
        'inv-plain-001',
        'Plain text only',
        new Usage(promptTokens: 1, completionTokens: 1),
        new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );

    expect(StructuredAgentResponseMapper::mapStructuredPayload($response))->toBeNull();
});
