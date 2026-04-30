<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Support;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use JsonException;

final class StructuredEvaluationOutputNormalizer
{
    private const int REFUSAL_PAYLOAD_SCAN_MAX_DEPTH = 6;

    public function normalize(string $output): StructuredEvaluationOutputNormalizationResult
    {
        $trimmedOutput = trim($output);

        if ($trimmedOutput === '') {
            throw TextToStructuredEvaluationException::invalidJson($output);
        }

        $decoded = $this->tryDecodeObject($trimmedOutput);

        if ($decoded !== null) {
            return $this->normalizationResultFromDecoded(
                decoded: $decoded,
                rawOutput: $output,
                assumeRepaired: false,
            );
        }

        $embeddedJsonObject = $this->extractFirstJsonObject($trimmedOutput);

        if ($embeddedJsonObject !== null) {
            $decodedEmbeddedObject = $this->tryDecodeObject($embeddedJsonObject);

            if ($decodedEmbeddedObject !== null) {
                return $this->normalizationResultFromDecoded(
                    decoded: $decodedEmbeddedObject,
                    rawOutput: $output,
                    assumeRepaired: true,
                );
            }
        }

        if ($this->looksLikeRefusalText($trimmedOutput)) {
            throw TextToStructuredEvaluationException::refusedStructuredOutput($output);
        }

        throw TextToStructuredEvaluationException::invalidJson($output);
    }

    /**
     * @param array<mixed> $decoded
     */
    public function normalizeFromDecodedArray(array $decoded): StructuredEvaluationOutputNormalizationResult
    {
        $raw = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->normalizationResultFromDecoded(
            decoded: $decoded,
            rawOutput: $raw,
            assumeRepaired: false,
        );
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function tryDecodeObject(string $candidate): ?array
    {
        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function normalizationResultFromDecoded(
        array $decoded,
        string $rawOutput,
        bool $assumeRepaired,
    ): StructuredEvaluationOutputNormalizationResult {
        try {
            [$payload, $wasRepaired] = $this->normalizeDecodedPayload($decoded);
        } catch (TextToStructuredEvaluationException $exception) {
            $wrappedPayload = $this->extractWrappedPayload($decoded);

            $looksLikeRefusalPayload = $this->looksLikeRefusalPayload($decoded)
              || (is_array($wrappedPayload) && $this->looksLikeRefusalPayload($wrappedPayload));

            if (!$this->attemptsStructuredEvaluationPayload($decoded) && $looksLikeRefusalPayload) {
                throw TextToStructuredEvaluationException::refusedStructuredOutput($rawOutput);
            }

            throw $exception;
        }

        return new StructuredEvaluationOutputNormalizationResult(
            status: $assumeRepaired || $wasRepaired
            ? StructuredEvaluationOutputNormalizationResult::STATUS_REPAIRED
            : StructuredEvaluationOutputNormalizationResult::STATUS_VALID,
            payload: $payload,
        );
    }

    /**
     * @param array<mixed> $decoded
     * @return array{
     *   0: array{
     *     summary:string,
     *     recommended_action:string,
     *     confidence:float,
     *     dimensions:array<string, array{score:int,summary:string,evidence:list<string>}>
     *   },
     *   1: bool
     * }
     */
    private function normalizeDecodedPayload(array $decoded): array
    {
        if ($this->isStructuredEvaluationPayload($decoded)) {
            return [$this->validatePayload($decoded), false];
        }

        $wrappedPayload = $this->extractWrappedPayload($decoded);

        if ($wrappedPayload !== null) {
            return [$this->validatePayload($wrappedPayload), true];
        }

        return [$this->validatePayload($decoded), false];
    }

    /**
     * @param array<mixed> $decoded
     */
    private function isStructuredEvaluationPayload(array $decoded): bool
    {
        return array_key_exists('summary', $decoded)
          && array_key_exists('recommended_action', $decoded)
          && array_key_exists('confidence', $decoded)
          && array_key_exists('dimensions', $decoded);
    }

    /**
     * @param array<mixed> $decoded
     * @return array{
     *   summary:string,
     *   recommended_action:string,
     *   confidence:float,
     *   dimensions:array<string, array{score:int,summary:string,evidence:list<string>}>
     * }
     */
    private function validatePayload(array $decoded): array
    {
        $summary = $decoded['summary'] ?? null;
        $recommendedAction = $decoded['recommended_action'] ?? null;
        $confidence = $decoded['confidence'] ?? null;
        $dimensions = $decoded['dimensions'] ?? null;

        if (!is_string($summary) || $summary === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('summary must be a non-empty string.');
        }

        if (!is_string($recommendedAction) || $recommendedAction === '') {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('recommended_action must be a non-empty string.');
        }

        if (!is_float($confidence) && !is_int($confidence)) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('confidence must be a float between 0.0 and 1.0.');
        }

        if (!is_array($dimensions) || $dimensions === []) {
            throw TextToStructuredEvaluationException::invalidSpecialistPayload('dimensions must be a non-empty object keyed by dimension name.');
        }

        $resolvedDimensions = [];

        foreach ($dimensions as $name => $dimension) {
            if (!is_string($name) || $name === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload('dimension keys must be non-empty strings.');
            }

            if (!is_array($dimension)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] must be an object.', $name),
                );
            }

            $score = $dimension['score'] ?? null;
            $dimensionSummary = $dimension['summary'] ?? null;
            $evidence = $dimension['evidence'] ?? [];

            if (!is_int($score) || $score < 0 || $score > 5) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] score must be an integer between 0 and 5.', $name),
                );
            }

            if (!is_string($dimensionSummary) || $dimensionSummary === '') {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] summary must be a non-empty string.', $name),
                );
            }

            if (!is_array($evidence)) {
                throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                    sprintf('dimension [%s] evidence must be a list of strings.', $name),
                );
            }

            $resolvedEvidence = [];

            foreach ($evidence as $item) {
                if (!is_string($item) || $item === '') {
                    throw TextToStructuredEvaluationException::invalidSpecialistPayload(
                        sprintf('dimension [%s] evidence entries must be non-empty strings.', $name),
                    );
                }

                $resolvedEvidence[] = $item;
            }

            $resolvedDimensions[$name] = [
              'score' => $score,
              'summary' => $dimensionSummary,
              'evidence' => $resolvedEvidence,
            ];
        }

        return [
          'summary' => $summary,
          'recommended_action' => $recommendedAction,
          'confidence' => (float)$confidence,
          'dimensions' => $resolvedDimensions,
        ];
    }

    /**
     * @param array<mixed> $decoded
     * @return array<mixed>|null
     */
    private function extractWrappedPayload(array $decoded): ?array
    {
        foreach (['result', 'response', 'output', 'data', 'payload'] as $key) {
            $candidate = $decoded[$key] ?? null;

            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function looksLikeRefusalPayload(array $decoded): bool
    {
        foreach (['refusal', 'message', 'text', 'error', 'detail', 'reason'] as $key) {
            $candidate = $decoded[$key] ?? null;

            if ($this->candidateContainsRefusalText($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function candidateContainsRefusalText(mixed $candidate): bool
    {
        return $this->candidateContainsRefusalTextAtDepth($candidate, 0);
    }

    private function candidateContainsRefusalTextAtDepth(mixed $candidate, int $depth): bool
    {
        if (is_string($candidate)) {
            return $this->looksLikeRefusalText($candidate);
        }

        if (!is_array($candidate)) {
            return false;
        }

        if ($depth >= self::REFUSAL_PAYLOAD_SCAN_MAX_DEPTH) {
            return false;
        }

        foreach ($candidate as $value) {
            if ($this->candidateContainsRefusalTextAtDepth($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeRefusalText(string $text): bool
    {
        $collapsedWhitespace = preg_replace('/\s+/u', ' ', $text);

        if (!is_string($collapsedWhitespace)) {
            $collapsedWhitespace = $text;
        }

        $normalized = strtolower(trim($collapsedWhitespace));

        if ($normalized === '') {
            return false;
        }

        foreach (
          [
            "i can't",
            'i cannot',
            'unable to comply',
            'unable to provide',
            'cannot comply',
            'cannot provide',
            'cannot return',
            'must decline',
            'i must decline',
            'refuse to',
            'refusing to',
          ] as $needle
        ) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function attemptsStructuredEvaluationPayload(array $decoded): bool
    {
        if ($this->containsAnyStructuredPayloadKeys($decoded)) {
            return true;
        }

        $wrappedPayload = $this->extractWrappedPayload($decoded);

        return is_array($wrappedPayload) && $this->containsAnyStructuredPayloadKeys($wrappedPayload);
    }

    /**
     * @param array<mixed> $payload
     */
    private function containsAnyStructuredPayloadKeys(array $payload): bool
    {
        foreach (['summary', 'recommended_action', 'confidence', 'dimensions'] as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function extractFirstJsonObject(string $output): ?string
    {
        $length = strlen($output);
        $start = null;
        $depth = 0;
        $inString = false;
        $escaping = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $output[$index];

            if ($start === null) {
                if ($character === '{') {
                    $start = $index;
                    $depth = 1;
                    $inString = false;
                    $escaping = false;
                }

                continue;
            }

            if ($inString) {
                if ($escaping) {
                    $escaping = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaping = true;

                    continue;
                }

                if ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '{') {
                $depth++;

                continue;
            }

            if ($character !== '}') {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return substr($output, $start, ($index - $start) + 1);
            }
        }

        return null;
    }
}
