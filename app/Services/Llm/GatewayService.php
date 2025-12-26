<?php

namespace App\Services\Llm;

use App\Exceptions\LlmException;
use App\Models\LlmRequest;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GatewayService
{
    public function __construct(
        private LiteLlmClient $client,
        private LlmRouter $router,
    ) {}

    public function processRequest(
        array $payload,
        Project $project,
        ?string $userId = null,
        ?int $apiKeyId = null,
        array $headers = [],
    ): array {
        $requestId = $headers['x-request-id'] ?? (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $tier = $this->router->pickTier($payload['messages'] ?? [], $headers);
            $models = $this->router->pickModelsForTier($tier);

            if (empty($models)) {
                throw new LlmException('no_models_available', "No models available for tier: {$tier}");
            }

            $cacheKey = $this->getCacheKey($payload, $tier);
            $cacheHit = false;

            if ($this->shouldCache($payload)) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $cacheHit = true;
                    $response = $cached;
                }
            }

            if (!$cacheHit) {
                $response = $this->callWithFailover($payload, $models, $requestId);

                if ($this->shouldCache($payload)) {
                    Cache::put($cacheKey, $response, config('litellm.cache.ttl_seconds', 86400));
                }
            }

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->recordRequest(
                requestId: $requestId,
                project: $project,
                userId: $userId,
                apiKeyId: $apiKeyId,
                tier: $tier,
                modelRequested: $payload['model'] ?? null,
                modelUsed: $response['model'] ?? $models[0],
                provider: $this->extractProvider($response['model'] ?? $models[0]),
                promptTokens: $response['usage']['prompt_tokens'] ?? 0,
                completionTokens: $response['usage']['completion_tokens'] ?? 0,
                totalTokens: $response['usage']['total_tokens'] ?? 0,
                cost: $response['cost'] ?? null,
                latencyMs: $latencyMs,
                cacheHit: $cacheHit,
                statusCode: 200,
            );

            return $response;
        } catch (LlmException $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->recordRequest(
                requestId: $requestId,
                project: $project,
                userId: $userId,
                apiKeyId: $apiKeyId,
                tier: $tier ?? 'unknown',
                modelRequested: $payload['model'] ?? null,
                modelUsed: null,
                provider: null,
                promptTokens: 0,
                completionTokens: 0,
                totalTokens: 0,
                cost: null,
                latencyMs: $latencyMs,
                cacheHit: false,
                statusCode: $e->statusCode ?? 500,
                errorType: $e->errorType,
            );

            throw $e;
        }
    }

    private function callWithFailover(array $payload, array $models, string $requestId): array
    {
        $lastException = null;

        foreach ($models as $model) {
            try {
                $requestPayload = array_merge($payload, ['model' => $model]);
                return $this->client->chat($requestPayload, $requestId);
            } catch (LlmException $e) {
                $lastException = $e;

                if ($e->statusCode !== 429 && $e->statusCode !== 500 && $e->statusCode !== 503) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new LlmException('failover_exhausted', 'All models failed');
    }

    private function shouldCache(array $payload): bool
    {
        if (!config('litellm.cache.enabled', true)) {
            return false;
        }

        $temperature = $payload['temperature'] ?? 1;
        $stream = $payload['stream'] ?? false;

        return $temperature === 0 && !$stream;
    }

    private function getCacheKey(array $payload, string $tier): string
    {
        $cacheablePayload = [
            'messages' => $payload['messages'] ?? [],
            'model' => $payload['model'] ?? null,
            'temperature' => $payload['temperature'] ?? 1,
            'max_tokens' => $payload['max_tokens'] ?? null,
            'tier' => $tier,
        ];

        return 'llm:' . hash('sha256', json_encode($cacheablePayload));
    }

    private function recordRequest(
        string $requestId,
        Project $project,
        ?string $userId,
        ?int $apiKeyId,
        string $tier,
        ?string $modelRequested,
        ?string $modelUsed,
        ?string $provider,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        ?float $cost,
        int $latencyMs,
        bool $cacheHit,
        int $statusCode,
        ?string $errorType = null,
    ): void {
        LlmRequest::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'user_id' => $userId,
            'api_key_id' => $apiKeyId,
            'request_id' => $requestId,
            'route_tier' => $tier,
            'model_requested' => $modelRequested,
            'model_used' => $modelUsed,
            'provider' => $provider,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost' => $cost,
            'latency_ms' => $latencyMs,
            'cache_hit' => $cacheHit,
            'status_code' => $statusCode,
            'error_type' => $errorType,
        ]);
    }

    private function extractProvider(string $model): string
    {
        if (str_contains($model, 'claude')) {
            return 'anthropic';
        }
        if (str_contains($model, 'gpt')) {
            return 'openai';
        }
        return 'unknown';
    }
}

