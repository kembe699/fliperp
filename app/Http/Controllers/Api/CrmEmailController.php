<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmEmail\StoreCrmEmailRequest;
use App\Http\Resources\CrmEmailResource;
use App\Models\CrmEmail;
use App\Services\Crm\CrmEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmEmailController extends Controller
{
    public function __construct(protected CrmEmailService $crmEmailService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmEmail::class);

        $emails = $this->crmEmailService->paginate(
            $request->only('lead_id', 'deal_id', 'customer_id', 'sent_by'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(CrmEmailResource::collection($emails));
    }

    public function send(StoreCrmEmailRequest $request): JsonResponse
    {
        $this->authorize('create', CrmEmail::class);

        $email = $this->crmEmailService->send($request->validated());

        return $this->success(new CrmEmailResource($email), 'Email queued for delivery.', 201);
    }
}
