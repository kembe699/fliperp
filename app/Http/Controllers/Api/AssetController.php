<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\AssignAssetRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Requests\AssetMaintenanceLog\StoreAssetMaintenanceLogRequest;
use App\Http\Resources\AssetDepreciationScheduleResource;
use App\Http\Resources\AssetMaintenanceLogResource;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Services\Assets\AssetService;
use App\Services\Assets\DepreciationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        protected AssetService $assetService,
        protected DepreciationService $depreciationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $assets = $this->assetService->paginate($request->integer('per_page', 15));

        return $this->paginated(AssetResource::collection($assets));
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $asset = $this->assetService->create($request->validated());

        return $this->success(new AssetResource($asset), 'Asset created successfully.', 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return $this->success(new AssetResource($asset));
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = $this->assetService->update($asset, $request->validated());

        return $this->success(new AssetResource($asset), 'Asset updated successfully.');
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);

        $this->assetService->delete($asset);

        return $this->success(null, 'Asset deleted successfully.');
    }

    public function assign(AssignAssetRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('assign', $asset);

        $asset = $this->assetService->assign($asset, (int) $request->validated('employee_id'));

        return $this->success(new AssetResource($asset), 'Asset assigned successfully.');
    }

    public function dispose(Asset $asset): JsonResponse
    {
        $this->authorize('dispose', $asset);

        $asset = $this->assetService->dispose($asset);

        return $this->success(new AssetResource($asset), 'Asset disposed successfully.');
    }

    public function maintenanceLogs(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return $this->success(AssetMaintenanceLogResource::collection($asset->maintenanceLogs));
    }

    public function depreciationSchedules(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return $this->success(AssetDepreciationScheduleResource::collection($asset->depreciationSchedules));
    }

    public function storeMaintenanceLog(StoreAssetMaintenanceLogRequest $request, Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $log = $this->assetService->addMaintenanceLog($asset, $request->validated());

        return $this->success(new AssetMaintenanceLogResource($log), 'Maintenance log recorded successfully.', 201);
    }

    public function runDepreciation(): JsonResponse
    {
        $this->authorize('runDepreciation', Asset::class);

        $result = $this->depreciationService->run();

        return $this->success([
            'schedules_created' => count($result['schedules']),
            'journal_entry_id' => $result['journal_entry']?->id,
        ], 'Depreciation run completed successfully.');
    }
}
