<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use Laravel\Ai\Embeddings;
use RuntimeException;

final readonly class SdkEmbeddingsRuntime implements EmbeddingsRuntime
{
    public function embed(EmbeddingsRequest $request): EmbeddingsResult
    {
        $pending = Embeddings::for($request->inputs);

        if ($request->dimensions !== null) {
            $pending = $pending->dimensions($request->dimensions);
        }

        if ($request->timeout !== null) {
            $pending = $pending->timeout($request->timeout);
        }

        $response = $pending->generate($request->provider, $request->model);

        $provider = $response->meta->provider ?? 'unknown';
        $model = $response->meta->model ?? 'unknown';

        return new EmbeddingsResult(
            runId: $request->runId,
            vectors: self::normalizeEmbeddings($response->embeddings),
            tokenCount: $response->tokens,
            provider: $provider,
            model: $model,
            metadata: $request->metadata,
        );
    }

    /**
     * @return array<int, array<int, float>>
     */
    private static function normalizeEmbeddings(mixed $embeddings): array
    {
        if (!is_iterable($embeddings)) {
            throw new RuntimeException('Embeddings response must be iterable.');
        }

        $vectors = [];

        foreach ($embeddings as $index => $vector) {
            $formattedIndex = self::formatIndex($index);

            if (!is_array($vector)) {
                throw new RuntimeException(sprintf('Embeddings response vector at index %s must be a list of floats.', $formattedIndex));
            }

            $row = [];

            foreach ($vector as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new RuntimeException(sprintf('Embeddings response contains non-numeric value at index %s.', $formattedIndex));
                }

                $row[] = (float) $value;
            }

            $vectors[] = $row;
        }

        return $vectors;
    }

    private static function formatIndex(mixed $index): string
    {
        if (is_int($index) || is_string($index)) {
            return (string) $index;
        }

        return get_debug_type($index);
    }
}
