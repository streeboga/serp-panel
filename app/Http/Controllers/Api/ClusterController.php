<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    public function index(Request $request, int $categoryId): JsonResponse
    {
        $clusters = Cluster::where('category_id', $categoryId)
            ->withCount('keywords')
            ->get();

        return response()->json($clusters);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $cluster = Cluster::create($validated);

        return response()->json($cluster, 201);
    }

    public function update(Request $request, Cluster $cluster): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $cluster->update($validated);

        return response()->json($cluster);
    }

    public function destroy(Request $request, Cluster $cluster): JsonResponse
    {
        $cluster->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
