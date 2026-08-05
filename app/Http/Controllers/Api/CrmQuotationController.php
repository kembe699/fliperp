<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SendCrmQuotationToContactRequest;
use App\Http\Resources\CrmEmailResource;
use App\Models\CrmEmail;
use App\Models\Quotation;
use App\Services\Crm\CrmEmailService;
use Illuminate\Http\JsonResponse;

class CrmQuotationController extends Controller
{
    public function __construct(protected CrmEmailService $crmEmailService) {}

    public function sendToContact(SendCrmQuotationToContactRequest $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);
        $this->authorize('create', CrmEmail::class);

        $email = $this->crmEmailService->sendQuotationToContact($quotation, $request->validated());

        return $this->success(new CrmEmailResource($email), 'Quotation queued for delivery.', 201);
    }
}
