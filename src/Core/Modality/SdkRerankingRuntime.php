<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use Laravel\Ai\Reranking;

final readonly class SdkRerankingRuntime implements RerankingRuntime
{
    public function rerank(RerankingRequest $request): RerankingResult
    {
        $pending = Reranking::of($request->documents);

        if ($request->limit !== null) {
            $pending = $pending->limit($request->limit);
        }

        $response = $pending->rerank($request->query, $request->provider, $request->model);

        $documents = [];

        foreach ($response->results as $ranked) {
            $documents[] = new RerankedDocumentResult(
                originalIndex: $ranked->index,
                document: $ranked->document,
                score: $ranked->score,
            );
        }

        $provider = $response->meta->provider ?? 'unknown';
        $model = $response->meta->model ?? 'unknown';

        return new RerankingResult(
            runId: $request->runId,
            documents: $documents,
            provider: $provider,
            model: $model,
            metadata: $request->metadata,
        );
    }
}
