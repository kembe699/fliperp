<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceivedNote\StoreGoodsReceivedNoteRequest;
use App\Http\Requests\GoodsReceivedNote\UpdateGoodsReceivedNoteRequest;
use App\Http\Resources\GoodsReceivedNoteResource;
use App\Models\Company;
use App\Models\GoodsReceivedNote;
use App\Services\Procurement\GrnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(protected GrnService $grnService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GoodsReceivedNote::class);

        $grns = $this->grnService->paginate($request->integer('per_page', 15));

        return $this->paginated(GoodsReceivedNoteResource::collection($grns));
    }

    public function store(StoreGoodsReceivedNoteRequest $request): JsonResponse
    {
        $this->authorize('create', GoodsReceivedNote::class);

        $grn = $this->grnService->create($request->validated());

        return $this->success(new GoodsReceivedNoteResource($grn), 'Goods received note created successfully.', 201);
    }

    public function show(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $this->authorize('view', $goodsReceivedNote);

        return $this->success(new GoodsReceivedNoteResource($goodsReceivedNote->load('items')));
    }

    public function update(UpdateGoodsReceivedNoteRequest $request, GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $this->authorize('update', $goodsReceivedNote);

        $goodsReceivedNote = $this->grnService->update($goodsReceivedNote, $request->validated());

        return $this->success(new GoodsReceivedNoteResource($goodsReceivedNote), 'Goods received note updated successfully.');
    }

    public function destroy(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $this->authorize('delete', $goodsReceivedNote);

        $this->grnService->delete($goodsReceivedNote);

        return $this->success(null, 'Goods received note deleted successfully.');
    }

    public function confirm(GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        $this->authorize('confirm', $goodsReceivedNote);

        $goodsReceivedNote = $this->grnService->confirm($goodsReceivedNote);

        return $this->success(new GoodsReceivedNoteResource($goodsReceivedNote), 'Goods received note confirmed successfully.');
    }

    public function pdf(Request $request, GoodsReceivedNote $goodsReceivedNote): Response
    {
        $this->authorize('view', $goodsReceivedNote);

        $goodsReceivedNote->load(['items.product', 'items.variant', 'supplier', 'warehouse', 'receivedBy', 'purchaseOrder']);
        $company = Company::find($goodsReceivedNote->company_id);

        $pdf = Pdf::loadView('pdf.grn', [
            'grn' => $goodsReceivedNote,
            'company' => $company,
            'supplier' => $goodsReceivedNote->supplier,
            'warehouse' => $goodsReceivedNote->warehouse,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');

        return $this->pdfResponse($pdf, "grn-{$goodsReceivedNote->reference_number}.pdf", $request->boolean('download'));
    }
}
