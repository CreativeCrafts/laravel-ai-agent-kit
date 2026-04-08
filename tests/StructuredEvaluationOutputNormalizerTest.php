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

it('throws a typed refusal exception when refusal is provided in an explicit refusal field', function (string $field): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        $field => 'I cannot provide the requested JSON response for this evaluation.',
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
})->with([
  'refusal',
  'message',
  'text',
  'error',
  'detail',
  'reason',
]);

it('throws a typed refusal exception when refusal is provided in an explicit nested refusal field', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'error' => [
          'message' => 'I cannot provide the requested JSON response for this evaluation.',
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
});

it('throws a typed refusal exception when refusal is nested under indexed arrays', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'error' => [
          [
            'message' => 'I cannot provide the requested JSON response for this evaluation.',
          ],
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
});

it('throws a typed refusal exception when refusal payload is wrapped under result', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'result' => [
          'refusal' => 'I cannot provide the requested JSON response for this evaluation.',
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
});

it('throws a typed refusal exception when nested refusal payload is wrapped under output', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'output' => [
          'error' => [
            'message' => 'I cannot provide the requested JSON response for this evaluation.',
          ],
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );
});

it('limits refusal payload scanning depth and keeps over-limit payloads as validation errors', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'error' => [
          'level_1' => [
            'level_2' => [
              'level_3' => [
                'level_4' => [
                  'level_5' => [
                    'message' => 'I cannot provide the requested JSON response for this evaluation.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'refused to return structured output',
        );

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'error' => [
          'level_1' => [
            'level_2' => [
              'level_3' => [
                'level_4' => [
                  'level_5' => [
                    'level_6' => [
                      'message' => 'I cannot provide the requested JSON response for this evaluation.',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'summary must be a non-empty string',
        );
});

it('keeps structured-validation failures as validation errors even with refusal-like metadata', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'summary' => 'The answer is clear and complete.',
        'recommended_action' => 'Approve the response.',
        'confidence' => 'high',
        'dimensions' => [
          'clarity' => [
            'score' => 5,
            'summary' => 'The recommendation is easy to follow.',
            'evidence' => ['The call to action is explicit.'],
          ],
        ],
        'message' => 'I cannot provide additional context right now.',
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'confidence must be a float between 0.0 and 1.0',
        );
});

it('does not classify non-explicit fields as refusal payloads', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    expect(fn ()
        => $normalizer->normalize(
            json_encode([
        'note' => 'I cannot provide more context right now.',
      ], JSON_THROW_ON_ERROR),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'summary must be a non-empty string',
        );
});

it('does not misclassify valid structured payload fields that contain refusal-like wording', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    $result = $normalizer->normalize(
        json_encode([
        'summary' => 'The user says "I cannot log in" after resetting the password.',
        'recommended_action' => 'Escalate and investigate why the user cannot authenticate.',
        'confidence' => 0.91,
        'dimensions' => [
          'accuracy' => [
            'score' => 4,
            'summary' => 'The report preserves the user\'s complaint context.',
            'evidence' => ['The quoted complaint includes "I cannot log in" verbatim.'],
          ],
        ],
      ], JSON_THROW_ON_ERROR),
    );

    expect($result->status)
      ->toBe(StructuredEvaluationOutputNormalizationResult::STATUS_VALID)
      ->and($result->payload['summary'])->toContain('I cannot log in');
});

it('prioritizes valid structured payloads over refusal-like metadata fields', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();

    $result = $normalizer->normalize(
        json_encode([
        'summary' => 'The response is complete and actionable.',
        'recommended_action' => 'Proceed with approval.',
        'confidence' => 0.94,
        'dimensions' => [
          'clarity' => [
            'score' => 5,
            'summary' => 'The recommendation is unambiguous.',
            'evidence' => ['The final action is explicitly stated.'],
          ],
        ],
        'message' => 'I cannot provide the requested JSON response for this evaluation.',
      ], JSON_THROW_ON_ERROR),
    );

    expect($result->status)
      ->toBe(StructuredEvaluationOutputNormalizationResult::STATUS_VALID)
      ->and($result->payload['recommended_action'])->toBe('Proceed with approval.');
});

it('redacts raw output from invalid-json exception messages', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();
    $output = 'secret-api-key-42 not-json';

    $exception = null;

    try {
        $normalizer->normalize($output);
    } catch (TextToStructuredEvaluationException $caught) {
        $exception = $caught;
    }

    expect($exception)
      ->toBeInstanceOf(TextToStructuredEvaluationException::class);

    $message = $exception?->getMessage() ?? '';

    expect($message)
      ->toContain('must be valid JSON')
      ->toContain('[redacted output; length=')
      ->not->toContain($output)
      ->not->toContain('secret-api-key-42');
});

it('redacts raw output from refusal exception messages', function (): void {
    $normalizer = new StructuredEvaluationOutputNormalizer();
    $output = 'I cannot provide the requested JSON response for this evaluation. secret-api-key-99';

    $exception = null;

    try {
        $normalizer->normalize($output);
    } catch (TextToStructuredEvaluationException $caught) {
        $exception = $caught;
    }

    expect($exception)
      ->toBeInstanceOf(TextToStructuredEvaluationException::class);

    $message = $exception?->getMessage() ?? '';

    expect($message)
      ->toContain('refused to return structured output')
      ->toContain('[redacted output; length=')
      ->not->toContain($output)
      ->not->toContain('secret-api-key-99');
});
