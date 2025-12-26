<?php

namespace App\Http\Middleware;

use App\Models\UsageDailyAggregate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->get('project');

        if (!$project) {
            return response()->json([
                'error' => [
                    'type' => 'internal_error',
                    'message' => 'Project not found in request context',
                    'request_id' => $request->header('x-request-id'),
                ],
            ], 500);
        }

        $currentMonth = now()->format('Y-m');
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $monthlyUsage = UsageDailyAggregate::where('project_id', $project->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('SUM(total_tokens) as total_tokens, SUM(total_cost) as total_cost')
            ->first();

        $usedTokens = $monthlyUsage->total_tokens ?? 0;
        $usedCost = $monthlyUsage->total_cost ?? 0;

        $estimatedTokens = $request->input('max_tokens', 4096);

        if ($project->monthly_token_limit && ($usedTokens + $estimatedTokens) > $project->monthly_token_limit) {
            return response()->json([
                'error' => [
                    'type' => 'quota_exceeded',
                    'message' => 'Monthly token limit exceeded',
                    'request_id' => $request->header('x-request-id'),
                    'details' => [
                        'used_tokens' => $usedTokens,
                        'limit' => $project->monthly_token_limit,
                    ],
                ],
            ], 429);
        }

        $estimatedCost = $this->estimateCost($estimatedTokens);

        if ($project->monthly_cost_limit && ($usedCost + $estimatedCost) > $project->monthly_cost_limit) {
            return response()->json([
                'error' => [
                    'type' => 'quota_exceeded',
                    'message' => 'Monthly cost limit exceeded',
                    'request_id' => $request->header('x-request-id'),
                    'details' => [
                        'used_cost' => (float) $usedCost,
                        'limit' => (float) $project->monthly_cost_limit,
                    ],
                ],
            ], 429);
        }

        return $next($request);
    }

    private function estimateCost(int $tokens): float
    {
        return $tokens * 0.00001;
    }
}

