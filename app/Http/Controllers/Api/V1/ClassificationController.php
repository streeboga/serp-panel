<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassificationRule\StoreClassificationRuleRequest;
use App\Http\Requests\ClassificationRule\UpdateClassificationRuleRequest;
use App\Http\Resources\ClassificationRuleResource;
use App\Http\Resources\DomainClassificationResource;
use App\Http\Resources\SiteTypeResource;
use App\Models\ClassificationRule;
use App\Services\ClassificationRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ClassificationController extends Controller
{
    public function __construct(
        private readonly ClassificationRuleService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = $this->service->listForOrganization($request->get('organization')->id);

        return ClassificationRuleResource::collection($rules);
    }

    public function store(StoreClassificationRuleRequest $request): JsonResponse
    {
        $data = $request->toDto()->toArray();
        $data['organization_id'] = $request->get('organization')->id;

        $rule = $this->service->create($data);

        return ClassificationRuleResource::make($rule)
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function show(ClassificationRule $rule): ClassificationRuleResource
    {
        $rule->load('siteType');

        return ClassificationRuleResource::make($rule);
    }

    public function update(UpdateClassificationRuleRequest $request, ClassificationRule $rule): ClassificationRuleResource
    {
        $rule = $this->service->update($rule, $request->toDto()->toArray());

        return ClassificationRuleResource::make($rule);
    }

    public function destroy(ClassificationRule $rule): JsonResponse
    {
        $this->service->delete($rule);

        return response()->json(null, 204);
    }

    public function classifyDomain(Request $request, string $domain): DomainClassificationResource
    {
        $validated = $request->validate([
            'site_type_id' => 'required|exists:site_types,id',
        ]);

        $classification = $this->service->classifyDomain(
            $domain,
            $request->get('organization')->id,
            (int) $validated['site_type_id'],
        );

        return DomainClassificationResource::make($classification);
    }

    public function siteTypes(): AnonymousResourceCollection
    {
        return SiteTypeResource::collection($this->service->siteTypes());
    }
}
