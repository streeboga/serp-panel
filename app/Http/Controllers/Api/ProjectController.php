<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->get('organization')->projects()->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = $request->get('organization')->projects()->create($validated);

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($project->loadCount('domains'));
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $project->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
