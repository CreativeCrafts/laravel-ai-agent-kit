<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use Laravel\Ai\Reranking;

final readonly class SdkRerankingRuntime implements RerankingRuntime
{
    public function __construct(
        private ProviderTargetResolver $providerTargetResolver,
    ) {
    }

    public function rerank(RerankingRequest $request): RerankingResult
    {
        $pending = Reranking::of($request->documents);
        $target = $this->providerTargetResolver->resolveExplicit($request->provider, $request->model);

        if ($request->limit !== null) {
            $pending = $pending->limit($request->limit);
        }

        $response = $pending->rerank($request->query, $target->sdkProviderName, $target->model);

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
