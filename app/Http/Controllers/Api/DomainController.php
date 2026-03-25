<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($project->domains);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if ($project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'is_own' => 'boolean',
        ]);

        $domain = $project->domains()->create($validated);

        return response()->json($domain, 201);
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'is_own' => 'boolean',
        ]);

        $domain->update($validated);

        return response()->json($domain);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        if ($domain->project->organization_id !== $request->get('organization')->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $domain->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
