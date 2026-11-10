<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\RfqResource;
use App\Models\Rfq;
use App\Services\Procurement\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Procurement > Requests for Quotation. */
class RfqController extends Controller
{
    public function __construct(protected RfqService $rfqs) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Rfq::class);

        return $this->paginated(RfqResource::collection($this->rfqs->paginate($request->integer('per_page', 15))));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Rfq::class);

        $rfq = $this->rfqs->create($this->validated($request, true));

        return $this->success(new RfqResource($rfq), 'Request for quotation created.', 201);
    }

    public function show(Rfq $rfq): JsonResponse
    {
        $this->authorize('view', $rfq);

        return $this->success(new RfqResource($rfq->load(RfqService::LOADED)));
    }

    public function update(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);

        return $this->success(new RfqResource($this->rfqs->update($rfq, $this->validated($request, false, $rfq))), 'Request for quotation updated.');
    }

    public function destroy(Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);
        $this->rfqs->delete($rfq);

        return $this->success(null, 'Request for quotation deleted.');
    }

    public function pdf(Request $request, Rfq $rfq): Response
    {
        $this->authorize('view', $rfq);

        return $this->pdfResponse($this->rfqs->pdf($rfq), "rfq-{$rfq->reference_number}.pdf", $request->boolean('download'));
    }

    /** Email it to the chosen suppliers on the RFQ, or to all of them. */
    public function send(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);
        $data = $request->validate(['supplier_ids' => ['nullable', 'array'], 'supplier_ids.*' => ['integer']]);

        $result = $this->rfqs->send($rfq, $data['supplier_ids'] ?? null);
        $message = count($result['sent']).' supplier'.(count($result['sent']) === 1 ? '' : 's').' emailed.'
            .($result['skipped'] ? ' No email address for: '.implode(', ', $result['skipped']).'.' : '');

        return $this->success($result + ['rfq' => new RfqResource($rfq->fresh(RfqService::LOADED))], $message);
    }

    /** Record what a supplier quoted. */
    public function quote(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'prices' => ['required', 'array'],
            'prices.*.rfq_item_id' => ['required', 'integer'],
            'prices.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->success(new RfqResource($this->rfqs->recordQuote($rfq, (int) $data['supplier_id'], $data)), 'Quote saved.');
    }

    /** Create a purchase order for the chosen supplier from this RFQ. */
    public function convert(Request $request, Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);
        abort_unless($request->user()->can('purchase-orders.create'), 403);
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $po = $this->rfqs->convert($rfq, (int) $data['supplier_id'], $data);

        return $this->success(new PurchaseOrderResource($po), "Purchase order {$po->reference_number} created.", 201);
    }

    public function cancel(Rfq $rfq): JsonResponse
    {
        $this->authorize('manage', $rfq);

        return $this->success(new RfqResource($this->rfqs->cancel($rfq)), 'Request for quotation cancelled.');
    }

    protected function validated(Request $request, bool $creating, ?Rfq $rfq = null): array
    {
        $companyId = $request->user()->company_id;
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'reference_number' => ['nullable', 'string', 'max:255', Rule::unique('rfqs', 'reference_number')->where('company_id', $companyId)->ignore($rfq?->id)],
            'title' => [$req, 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'response_due_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string'],
            'items' => [$req, 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'supplier_ids' => [$req, 'array', 'min:1'],
            'supplier_ids.*' => ['integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
        ]);
    }
}
