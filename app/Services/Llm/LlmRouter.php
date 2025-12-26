<?php

namespace App\Services\Llm;

class LlmRouter
{
    public function pickTier(array $messages, array $headers): string
    {
        if ($headers['x-quality'] ?? null === 'deep') {
            return 'deep';
        }

        $totalChars = collect($messages)
            ->sum(fn($msg) => strlen($msg['content'] ?? ''));

        $threshold = config('litellm.routing.large_request_threshold', 8000);

        return $totalChars >= $threshold ? 'deep' : 'fast';
    }

    public function pickModelsForTier(string $tier): array
    {
        return config("litellm.models.{$tier}", []);
    }

    public function getNextModel(string $tier, ?string $currentModel = null): ?string
    {
        $models = $this->pickModelsForTier($tier);

        if (empty($models)) {
            return null;
        }

        if (!$currentModel) {
            return $models[0];
        }

        $currentIndex = array_search($currentModel, $models, true);

        if ($currentIndex === false || $currentIndex === count($models) - 1) {
            return null;
        }

        return $models[$currentIndex + 1];
    }
}

