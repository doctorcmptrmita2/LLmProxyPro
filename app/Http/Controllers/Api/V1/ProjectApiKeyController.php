<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectApiKeyController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $plainKey = 'sk_' . Str::random(32);
        $keyHash = Hash::make($plainKey);

        $apiKey = ProjectApiKey::create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'key_hash' => $keyHash,
        ]);

        return response()->json([
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'key' => $plainKey,
            'created_at' => $apiKey->created_at,
        ], 201);
    }

    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $keys = $project->apiKeys()
            ->select('id', 'name', 'last_used_at', 'revoked_at', 'created_at')
            ->get();

        return response()->json($keys);
    }

    public function destroy(Project $project, ProjectApiKey $apiKey): JsonResponse
    {
        $this->authorize('update', $project);

        if ($apiKey->project_id !== $project->id) {
            return response()->json(['error' => 'Key not found'], 404);
        }

        $apiKey->update(['revoked_at' => now()]);

        return response()->json(null, 204);
    }
}

