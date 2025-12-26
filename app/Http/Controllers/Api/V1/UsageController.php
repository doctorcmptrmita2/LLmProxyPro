<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\UsageDailyAggregate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'project_id' => 'required|exists:projects,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->authorize('view', $project);

        $usage = UsageDailyAggregate::where('project_id', $project->id)
            ->whereBetween('date', [$validated['from'], $validated['to']])
            ->orderBy('date')
            ->get();

        return response()->json([
            'project_id' => $project->id,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'data' => $usage,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'project_id' => 'required|exists:projects,id',
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $this->authorize('view', $project);

        [$year, $month] = explode('-', $validated['month']);

        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $summary = UsageDailyAggregate::where('project_id', $project->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(total_tokens) as total_tokens,
                SUM(total_cost) as total_cost,
                SUM(request_count) as request_count
            ')
            ->first();

        return response()->json([
            'project_id' => $project->id,
            'month' => $validated['month'],
            'total_tokens' => $summary->total_tokens ?? 0,
            'total_cost' => $summary->total_cost ?? 0,
            'request_count' => $summary->request_count ?? 0,
        ]);
    }
}

