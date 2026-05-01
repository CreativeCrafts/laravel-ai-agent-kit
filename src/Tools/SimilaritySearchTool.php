<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use JsonException;
use RuntimeException;

/**
 * Vector similarity search over {@see VectorStoreInterface}, aligned with Laravel AI's
 * `SimilaritySearch` tool shape (query string in, ranked document metadata out).
 */
final readonly class SimilaritySearchTool implements Tool
{
    public function __construct(
        private EmbeddingsRuntime $embeddingsRuntime,
        private VectorStoreInterface $vectorStore,
        private ConfigRepository $config,
    ) {
    }

    public function name(): string
    {
        $name = $this->config->get('ai-agent-kit.tools.similarity_search.name');

        return is_string($name) && $name !== '' ? $name : 'similarity_search';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Search stored vector documents for text similar to the query.',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Natural-language search query.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results (defaults to package configuration).',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Vector store namespace to search (defaults to package configuration).',
                ],
                'filter_json' => [
                    'type' => 'string',
                    'description' => 'Optional JSON object string: metadata keys must match document metadata (equality).',
                ],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(array $input): array
    {
        $query = $input['query'] ?? null;

        if (!is_string($query) || trim($query) === '') {
            throw new RuntimeException('Similarity search requires a non-empty string [query].');
        }

        $namespace = $this->resolveNamespace($input);
        $limit = $this->resolveLimit($input);
        $filter = $this->resolveFilterFromInput($input);

        $embeddingRequest = new EmbeddingsRequest(
            runId: 'tool:similarity_search',
            inputs: [$query],
            dimensions: $this->embeddingDimensions(),
            timeout: $this->embeddingTimeout(),
            provider: $this->embeddingProvider(),
            model: $this->embeddingModel(),
        );

        $embedded = $this->embeddingsRuntime->embed($embeddingRequest);

        if ($embedded->vectors === []) {
            throw new RuntimeException('Embeddings runtime returned no vectors for the query.');
        }

        $vector = $embedded->vectors[0];

        $searchQuery = new VectorSearchQuery(
            embedding: $vector,
            limit: $limit,
            filter: $filter,
        );

        $hits = $this->vectorStore->search($namespace, $searchQuery);

        $rows = [];

        foreach ($hits as $hit) {
            $rows[] = [
                'id' => $hit->id,
                'score' => $hit->score,
                'metadata' => $hit->metadata,
            ];
        }

        if ($rows === []) {
            return [
                'empty' => true,
                'message' => 'No relevant results found.',
                'results' => [],
            ];
        }

        return [
            'empty' => false,
            'message' => 'Relevant results found (ordered by similarity, highest first).',
            'results' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveNamespace(array $input): string
    {
        $explicit = $input['namespace'] ?? null;

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $configured = $this->config->get('ai-agent-kit.tools.similarity_search.default_namespace');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return 'default';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveLimit(array $input): int
    {
        $explicit = $input['limit'] ?? null;

        if (is_int($explicit) && $explicit >= 1) {
            return $explicit;
        }

        $configured = $this->config->get('ai-agent-kit.tools.similarity_search.default_limit');

        if (is_int($configured) && $configured >= 1) {
            return $configured;
        }

        return 10;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function resolveFilterFromInput(array $input): array
    {
        $raw = $input['filter_json'] ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        if (!is_string($raw)) {
            throw new RuntimeException('Similarity search [filter_json] must be a string when provided.');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Similarity search [filter_json] must be valid JSON.');
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Similarity search [filter_json] must decode to a JSON object.');
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function embeddingDimensions(): ?int
    {
        $value = $this->config->get('ai-agent-kit.tools.similarity_search.embedding_dimensions');

        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }

    private function embeddingTimeout(): ?int
    {
        $value = $this->config->get('ai-agent-kit.tools.similarity_search.embedding_timeout_seconds');

        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }

    private function embeddingProvider(): ?string
    {
        $value = $this->config->get('ai-agent-kit.tools.similarity_search.embedding_provider');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function embeddingModel(): ?string
    {
        $value = $this->config->get('ai-agent-kit.tools.similarity_search.embedding_model');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
