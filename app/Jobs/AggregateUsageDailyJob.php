<?php

namespace App\Jobs;

use App\Models\LlmRequest;
use App\Models\UsageDailyAggregate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateUsageDailyJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $aggregates = LlmRequest::whereDate('created_at', $yesterday)
            ->where('status_code', 200)
            ->groupBy('project_id')
            ->selectRaw('
                project_id,
                SUM(total_tokens) as total_tokens,
                SUM(cost) as total_cost,
                COUNT(*) as request_count
            ')
            ->get();

        foreach ($aggregates as $aggregate) {
            UsageDailyAggregate::updateOrCreate(
                [
                    'project_id' => $aggregate->project_id,
                    'date' => $yesterday,
                ],
                [
                    'total_tokens' => $aggregate->total_tokens,
                    'total_cost' => $aggregate->total_cost,
                    'request_count' => $aggregate->request_count,
                ]
            );
        }
    }
}

