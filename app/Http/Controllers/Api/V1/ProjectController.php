<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:255',
            'plan_code' => 'nullable|string',
            'monthly_token_limit' => 'nullable|integer|min:1',
            'monthly_cost_limit' => 'nullable|numeric|min:0',
        ]);

        $org = Organization::findOrFail($validated['organization_id']);
        $this->authorize('update', $org);

        $project = Project::create($validated);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project);
    }
}

