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

        $vectors = [];

        /** @var mixed $embeddings */
        $embeddings = $response->embeddings;

        if (!is_iterable($embeddings)) {
            throw new RuntimeException('Embeddings response must be iterable.');
        }

        foreach ($embeddings as $index => $vector) {
            if (!is_array($vector)) {
                throw new RuntimeException(sprintf('Embeddings response vector at index %s must be a list of floats.', $index));
            }

            $row = [];

            foreach ($vector as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new RuntimeException(sprintf('Embeddings response contains non-numeric value at index %s.', $index));
                }

                $row[] = (float) $value;
            }

            $vectors[] = $row;
        }

        return new EmbeddingsResult(
            runId: $request->runId,
            vectors: $vectors,
            tokenCount: $response->tokens,
            provider: $provider,
            model: $model,
            metadata: $request->metadata,
        );
    }
}
