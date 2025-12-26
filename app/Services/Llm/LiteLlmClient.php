<?php

namespace App\Services\Llm;

use App\Exceptions\LlmException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiteLlmClient
{
    private PendingRequest $client;

    public function __construct()
    {
        $baseUrl = config('litellm.base_url');
        $apiKey = config('litellm.api_key');
        $timeout = config('litellm.timeout');
        $connectTimeout = config('litellm.connect_timeout');

        $this->client = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout);

        if ($apiKey) {
            $this->client = $this->client->withToken($apiKey);
        }
    }

    public function chat(array $payload, string $requestId): array
    {
        $maxRetries = config('litellm.max_retries', 2);
        $retryDelayMs = config('litellm.retry_delay_ms', 500);

        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    usleep($retryDelayMs * 1000);
                }

                $response = $this->client
                    ->withHeaders(['X-Request-Id' => $requestId])
                    ->post('/v1/chat/completions', $payload);

                if ($response->failed()) {
                    $statusCode = $response->status();

                    if ($statusCode === 429 || $statusCode >= 500) {
                        if ($attempt < $maxRetries) {
                            continue;
                        }
                    }

                    throw new LlmException(
                        'api_error',
                        $response->json('error.message') ?? 'LiteLLM API error',
                        $statusCode,
                        $response->json()
                    );
                }

                return $response->json();
            } catch (LlmException $e) {
                $lastException = $e;
            } catch (\Exception $e) {
                $lastException = new LlmException(
                    'network_error',
                    $e->getMessage(),
                    null,
                    ['original_exception' => get_class($e)]
                );
            }
        }

        throw $lastException ?? new LlmException('unknown_error', 'Failed to call LiteLLM');
    }
}

