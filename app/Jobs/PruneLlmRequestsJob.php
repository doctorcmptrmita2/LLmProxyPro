<?php

namespace App\Jobs;

use App\Models\LlmRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneLlmRequestsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $retentionDays = config('llm.request_retention_days', 90);

        LlmRequest::where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}

