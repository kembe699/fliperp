<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Requests\Quotation\UpdateQuotationRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\QuotationResource;
use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function __construct(protected QuotationService $quotationService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = $this->quotationService->paginate(
            $request->only('status', 'branch_id', 'customer_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated(QuotationResource::collection($quotations));
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        $quotation = $this->quotationService->create($request->validated());

        return $this->success(new QuotationResource($quotation), 'Quotation created successfully.', 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        return $this->success(new QuotationResource($quotation->load('items')));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        $this->authorize('update', $quotation);

        $quotation = $this->quotationService->update($quotation, $request->validated());

        return $this->success(new QuotationResource($quotation), 'Quotation updated successfully.');
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $this->authorize('delete', $quotation);

        $this->quotationService->delete($quotation);

        return $this->success(null, 'Quotation deleted successfully.');
    }

    public function send(Quotation $quotation): JsonResponse
    {
        $this->authorize('send', $quotation);

        $quotation = $this->quotationService->send($quotation);

        return $this->success(new QuotationResource($quotation), 'Quotation sent successfully.');
    }

    public function accept(Quotation $quotation): JsonResponse
    {
        $this->authorize('accept', $quotation);

        $quotation = $this->quotationService->accept($quotation);

        return $this->success(new QuotationResource($quotation), 'Quotation accepted successfully.');
    }

    public function reject(Quotation $quotation): JsonResponse
    {
        $this->authorize('reject', $quotation);

        $quotation = $this->quotationService->reject($quotation);

        return $this->success(new QuotationResource($quotation), 'Quotation rejected successfully.');
    }

    public function convertToInvoice(Quotation $quotation): JsonResponse
    {
        $this->authorize('convertToInvoice', $quotation);

        $invoice = $this->quotationService->convertToInvoice($quotation);

        return $this->success(new InvoiceResource($invoice), 'Quotation converted to invoice successfully.', 201);
    }

    public function pdf(Request $request, Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);

        $pdf = $this->buildPdf($quotation);

        return $this->pdfResponse($pdf, "quotation-{$quotation->reference_number}.pdf", $request->boolean('download'));
    }

    public function email(Quotation $quotation): JsonResponse
    {
        $this->authorize('view', $quotation);

        $quotation->loadMissing('customer');
        $customer = $quotation->customer;

        if (! $customer?->email) {
            throw ValidationException::withMessages([
                'email' => ['This customer has no email address on file.'],
            ]);
        }

        $company = Company::find($quotation->company_id);
        $pdf = $this->buildPdf($quotation);

        Mail::to($customer->email)->send(new DocumentMail(
            documentType: 'Quotation',
            referenceNumber: $quotation->reference_number,
            recipientName: $customer->name,
            companyName: $company?->name ?? config('app.name'),
            pdfContent: $pdf->output(),
            pdfFilename: "quotation-{$quotation->reference_number}.pdf",
        ));

        return $this->success(null, "Quotation emailed to {$customer->email}.");
    }

    protected function buildPdf(Quotation $quotation): DomPdf
    {
        $quotation->loadMissing(['items.product', 'items.variant', 'customer', 'branch']);
        $company = Company::find($quotation->company_id);

        return Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
            'branch' => $quotation->branch,
            'customer' => $quotation->customer,
            'currencyCode' => $company?->currency_code ?? 'USD',
        ])->setPaper('a4', 'portrait');
    }
}
