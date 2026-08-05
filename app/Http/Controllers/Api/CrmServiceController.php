<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmService\StoreCrmServiceRequest;
use App\Http\Requests\CrmService\UpdateCrmServiceRequest;
use App\Http\Resources\CrmServiceResource;
use App\Models\CrmService;
use App\Services\Crm\ServiceCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmServiceController extends Controller
{
    public function __construct(protected ServiceCatalogService $serviceCatalogService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmService::class);

        $services = $this->serviceCatalogService->paginate(
            $request->only('category', 'is_active'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmServiceResource::collection($services));
    }

    public function store(StoreCrmServiceRequest $request): JsonResponse
    {
        $this->authorize('create', CrmService::class);

        $service = $this->serviceCatalogService->create($request->validated());

        return $this->success(new CrmServiceResource($service), 'Service created successfully.', 201);
    }

    public function show(CrmService $crmService): JsonResponse
    {
        $this->authorize('view', $crmService);

        return $this->success(new CrmServiceResource($crmService));
    }

    public function update(UpdateCrmServiceRequest $request, CrmService $crmService): JsonResponse
    {
        $this->authorize('update', $crmService);

        $service = $this->serviceCatalogService->update($crmService, $request->validated());

        return $this->success(new CrmServiceResource($service), 'Service updated successfully.');
    }

    public function destroy(CrmService $crmService): JsonResponse
    {
        $this->authorize('delete', $crmService);

        $this->serviceCatalogService->delete($crmService);

        return $this->success(null, 'Service deleted successfully.');
    }
}
