<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Sales\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->paginate(
            $request->only('status', 'branch_id', 'customer_id', 'from', 'to'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(InvoiceResource::collection($invoices));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request->validated());

        return $this->success(new InvoiceResource($invoice), 'Invoice created successfully.', 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice = $this->invoiceService->refreshStatus($invoice);

        return $this->success(new InvoiceResource($invoice->load('items')));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return $this->success(new InvoiceResource($invoice), 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice);

        return $this->success(null, 'Invoice deleted successfully.');
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        $invoice = $this->invoiceService->send($invoice);

        return $this->success(new InvoiceResource($invoice), 'Invoice sent successfully.');
    }

    public function cancel(Invoice $invoice): JsonResponse
    {
        $this->authorize('cancel', $invoice);

        $invoice = $this->invoiceService->cancel($invoice);

        return $this->success(new InvoiceResource($invoice), 'Invoice cancelled successfully.');
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load(['items.product', 'items.variant', 'customer', 'branch']);
        $company = Company::find($invoice->company_id);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'branch' => $invoice->branch,
            'customer' => $invoice->customer,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');

        return $this->pdfResponse($pdf, "invoice-{$invoice->reference_number}.pdf", $request->boolean('download'));
    }

    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->overdue(
            $request->only('branch_id', 'customer_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(InvoiceResource::collection($invoices));
    }
}
