<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;

class EmbeddingCascadeService
{
    protected AiManager $ai;

    public function __construct()
    {
        $this->ai = app(AiManager::class);
    }

    /**
     * Embed text inputs using cascade fallback.
     *
     * @param array $inputs
     * @param string|null $targetModel
     * @return EmbeddingsResponse
     * @throws \Exception
     */
    public function embed(array $inputs, ?string $targetModel = null): EmbeddingsResponse
    {
        $configuredNodes = array_values(config('ai.embedding_cascade.nodes', []));
        $enabled = config('ai.embedding_cascade.enabled', true);

        if (!$enabled || empty($configuredNodes)) {
            return $this->ai->textProvider()->embeddings(
                $inputs,
                config('ai.rag.embedding_dimensions', 1536),
                config('ai.rag.embedding_model', 'text-embedding-3-small')
            );
        }

        $nodes = $configuredNodes;
        if ($targetModel !== null) {
            $resolvedTargetModel = $this->resolveConfiguredModel($targetModel, $configuredNodes);
            if ($resolvedTargetModel === null) {
                $availableModels = array_values(array_unique(array_column($configuredNodes, 'model')));
                $message = "EmbeddingCascade: Forced model {$targetModel} is not mapped to any configured cascade node.";

                Log::error($message, [
                    'target_model' => $targetModel,
                    'available_models' => $availableModels,
                ]);

                throw new \RuntimeException($message);
            }

            $nodes = array_values(array_filter(
                $configuredNodes,
                fn(array $node) => $node['model'] === $resolvedTargetModel
            ));

            if ($resolvedTargetModel !== $targetModel) {
                Log::info('EmbeddingCascade: normalized target model to configured node', [
                    'requested_model' => $targetModel,
                    'resolved_model' => $resolvedTargetModel,
                ]);
            }
        }

        $errors = [];
        foreach ($nodes as $index => $node) {
            try {
                Log::info("EmbeddingCascade: Attempting node {$index}", [
                    'label' => $node['label'],
                    'model' => $node['model'],
                ]);

                $provider = $this->ai->textProvider($node['provider'], [
                    'api_key' => $node['api_key'],
                    'base_url' => $node['base_url'] ?? null,
                ]);

                $response = $provider->embeddings(
                    $inputs,
                    $node['dimensions'],
                    $node['model']
                );

                Log::info("EmbeddingCascade: Success using node {$index}", [
                    'label' => $node['label'],
                ]);

                return $this->canonicalizeResponse($response, $node);
            } catch (\Throwable $e) {
                $errorMsg = "Node {$index} ({$node['label']}) failed: " . $e->getMessage();
                Log::warning("EmbeddingCascade: {$errorMsg}");
                $errors[] = $errorMsg;
            }
        }

        $allErrors = implode("; ", $errors);
        Log::error("EmbeddingCascade: All nodes failed. Errors: {$allErrors}");

        throw new \Exception("Embedding cascade failed for all nodes. Errors: {$allErrors}");
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function resolveConfiguredModel(string $targetModel, array $nodes): ?string
    {
        $models = array_values(array_unique(array_column($nodes, 'model')));

        if (in_array($targetModel, $models, true)) {
            return $targetModel;
        }

        foreach ($models as $model) {
            if (str_starts_with($targetModel, $model)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function canonicalizeResponse(EmbeddingsResponse $response, array $node): EmbeddingsResponse
    {
        return new EmbeddingsResponse(
            embeddings: $response->embeddings,
            tokens: $response->tokens,
            meta: new Meta(
                provider: $response->meta->provider ?? ($node['provider'] ?? null),
                model: $node['model'],
                citations: $response->meta->citations
            )
        );
    }
}
