<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceGeofence\StoreAttendanceGeofenceRequest;
use App\Http\Requests\AttendanceGeofence\UpdateAttendanceGeofenceRequest;
use App\Http\Resources\AttendanceGeofenceResource;
use App\Models\AttendanceGeofence;
use App\Models\Branch;
use App\Services\Hr\AttendanceGeofenceService;
use App\Services\Hr\AttendanceQrTokenService;
use App\Support\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AttendanceGeofenceController extends Controller
{
    public function __construct(
        protected AttendanceGeofenceService $geofenceService,
        protected AttendanceQrTokenService $qrTokenService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceGeofence::class);

        $geofences = $this->geofenceService->paginate($request->only('branch_id'), $request->integer('per_page', 15));

        return $this->paginated(AttendanceGeofenceResource::collection($geofences));
    }

    public function store(StoreAttendanceGeofenceRequest $request): JsonResponse
    {
        $this->authorize('create', AttendanceGeofence::class);

        $geofence = $this->geofenceService->create($request->validated());

        return $this->success(new AttendanceGeofenceResource($geofence), 'Geofence created successfully.', 201);
    }

    public function show(AttendanceGeofence $attendanceGeofence): JsonResponse
    {
        $this->authorize('view', $attendanceGeofence);

        return $this->success(new AttendanceGeofenceResource($attendanceGeofence));
    }

    public function update(UpdateAttendanceGeofenceRequest $request, AttendanceGeofence $attendanceGeofence): JsonResponse
    {
        $this->authorize('update', $attendanceGeofence);

        $attendanceGeofence = $this->geofenceService->update($attendanceGeofence, $request->validated());

        return $this->success(new AttendanceGeofenceResource($attendanceGeofence), 'Geofence updated successfully.');
    }

    public function destroy(AttendanceGeofence $attendanceGeofence): JsonResponse
    {
        $this->authorize('delete', $attendanceGeofence);

        $this->geofenceService->delete($attendanceGeofence);

        return $this->success(null, 'Geofence deleted successfully.');
    }

    public function regenerateQrToken(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeBranchAccess($request, $branch, 'attendance-geofences.update');

        $token = $this->qrTokenService->regenerate($branch);

        return $this->success([
            'branch_id' => $branch->id,
            'token' => $token->token,
            'created_at' => $token->created_at?->toIso8601String(),
        ], 'QR token regenerated successfully.');
    }

    public function qrCode(Request $request, Branch $branch): Response
    {
        $this->authorizeBranchAccess($request, $branch, 'attendance-geofences.view');

        $token = $this->qrTokenService->currentToken($branch) ?? $this->qrTokenService->regenerate($branch);

        $url = Url::withScheme(config('app.frontend_url'))."/employee-portal/clock?branch={$branch->id}&token={$token->token}";

        $svg = $this->qrTokenService->svgFor($url);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    protected function authorizeBranchAccess(Request $request, Branch $branch, string $permission): void
    {
        abort_unless(
            $branch->company_id === $request->user()->company_id && $request->user()->can($permission),
            403,
        );
    }
}
