<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;

it('classifies direct valid structured output without repair', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    $result = $normalizer->normalize(
        json_encode([
        'summary' => 'The answer is concise and direct.',
        'recommended_action' => 'Send the response.',
        'confidence' => 0.95,
        'dimensions' => [
          'clarity' => [
            'score' => 5,
            'summary' => 'The wording is easy to follow.',
            'evidence' => ['The main recommendation is stated in the first sentence.'],
          ],
        ],
      ], JSON_THROW_ON_ERROR),
    );

    expect($result->status)
      ->toBe(StructuredEvaluationOutputNormalizationResult::STATUS_VALID)
      ->and($result->isValid())->toBeTrue()
      ->and($result->wasRepaired())->toBeFalse()
      ->and($result->payload['summary'])->toBe('The answer is concise and direct.')
      ->and($result->payload['recommended_action'])->toBe('Send the response.');
});

it('repairs wrapped structured output and classifies it as repaired', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    $result = $normalizer->normalize(
        <<<OUTPUT
          Here is the structured evaluation you requested:
          
          ```json
          {
            "summary": "The message is actionable and complete.",
            "recommended_action": "Approve the response with no edits.",
            "confidence": 0.9,
            "dimensions": {
              "accuracy": {
                "score": 4,
                "summary": "The core claim is supported by the provided text.",
                "evidence": ["The policy statement matches the supplied text."]
              }
            }
          }
          ```
          OUTPUT,
    );

    expect($result->status)
      ->toBe(StructuredEvaluationOutputNormalizationResult::STATUS_REPAIRED)
      ->and($result->isValid())->toBeFalse()
      ->and($result->wasRepaired())->toBeTrue()
      ->and($result->payload['summary'])->toBe('The message is actionable and complete.')
      ->and($result->payload['dimensions']['accuracy']['score'])->toBe(4);
});

it('throws a typed exception for refusal-style output', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            'I cannot provide the requested JSON response for this evaluation.',
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
});
