<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->auditLogService->paginate(
            $request->only(['module', 'user_id', 'date_from', 'date_to']),
            $request->integer('per_page', 15),
        );

        return $this->paginated(AuditLogResource::collection($logs));
    }
}
