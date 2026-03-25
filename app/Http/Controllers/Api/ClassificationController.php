<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassificationRule;
use App\Models\DomainClassification;
use App\Models\SiteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->get('organization')->id;

        $rules = ClassificationRule::where('organization_id', $orgId)
            ->orWhere('is_system', true)
            ->with('siteType')
            ->orderByDesc('priority')
            ->get();

        return response()->json($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_type' => 'required|string|in:domain_exact,domain_contains,domain_regex,url_regex,title_contains',
            'pattern' => 'required|string|max:1000',
            'site_type_id' => 'required|exists:site_types,id',
            'priority' => 'required|integer|min:0',
            'is_system' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $request->get('organization')->id;
        $rule = ClassificationRule::create($validated);

        return response()->json($rule, 201);
    }

    public function show(ClassificationRule $rule): JsonResponse
    {
        $rule->load('siteType');

        return response()->json($rule);
    }

    public function update(Request $request, ClassificationRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'rule_type' => 'sometimes|string|in:domain_exact,domain_contains,domain_regex,url_regex,title_contains',
            'pattern' => 'sometimes|string|max:1000',
            'site_type_id' => 'sometimes|exists:site_types,id',
            'priority' => 'sometimes|integer|min:0',
            'is_system' => 'nullable|boolean',
        ]);

        $rule->update($validated);

        return response()->json($rule);
    }

    public function destroy(ClassificationRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(null, 204);
    }

    public function classifyDomain(Request $request, string $domain): JsonResponse
    {
        $validated = $request->validate([
            'site_type_id' => 'required|exists:site_types,id',
        ]);

        $orgId = $request->get('organization')->id;

        $classification = DomainClassification::updateOrCreate(
            ['domain' => $domain, 'organization_id' => $orgId],
            ['site_type_id' => $validated['site_type_id'], 'classified_by' => 'manual', 'rule_id' => null],
        );

        return response()->json($classification);
    }

    public function siteTypes(): JsonResponse
    {
        return response()->json(SiteType::orderBy('sort_order')->get());
    }
}
