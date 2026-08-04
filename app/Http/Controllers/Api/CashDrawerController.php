<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashDrawer\CloseCashDrawerSessionRequest;
use App\Http\Requests\CashDrawer\OpenCashDrawerSessionRequest;
use App\Http\Resources\CashDrawerSessionResource;
use App\Models\CashDrawerSession;
use App\Services\Pos\CashDrawerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CashDrawerController extends Controller
{
    public function __construct(protected CashDrawerService $cashDrawerService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CashDrawerSession::class);

        $sessions = $this->cashDrawerService->paginate(
            $request->only('user_id', 'branch_id', 'status', 'from', 'to'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CashDrawerSessionResource::collection($sessions));
    }

    public function current(): JsonResponse
    {
        $this->authorize('viewAny', CashDrawerSession::class);

        $session = $this->cashDrawerService->current();

        return $this->success($session ? new CashDrawerSessionResource($session) : null);
    }

    public function open(OpenCashDrawerSessionRequest $request): JsonResponse
    {
        $this->authorize('open', CashDrawerSession::class);

        $session = $this->cashDrawerService->open($request->validated());

        return $this->success(new CashDrawerSessionResource($session), 'Cash drawer session opened successfully.', 201);
    }

    public function close(CloseCashDrawerSessionRequest $request): JsonResponse
    {
        $session = $this->cashDrawerService->current();

        if (! $session) {
            throw ValidationException::withMessages([
                'cash_drawer_session' => ['You do not have an open cash drawer session.'],
            ]);
        }

        $this->authorize('close', $session);

        $session = $this->cashDrawerService->close($session, $request->validated());

        return $this->success(new CashDrawerSessionResource($session), 'Cash drawer session closed successfully.');
    }
}
