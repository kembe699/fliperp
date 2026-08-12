<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\QuotationResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Deliberately thin — every quotation/invoice for a client is created through the
 * SAME QuotationService/InvoiceService normal tenant users go through, just with
 * customer_id forced to that client's billing_customer_id and company_id implicitly
 * the platform company (the acting platform-staff user's own home tenant — no
 * CompanyScope bypass needed, this genuinely IS the platform company's own data).
 * No parallel line-item/tax/total logic lives here.
 */
class PlatformBillingController extends Controller
{
    public function summary(): JsonResponse
    {
        $invoices = Invoice::all();

        $totalInvoiced = round((float) $invoices->sum('total_amount'), 2);
        $totalCollected = round((float) $invoices->sum('amount_paid'), 2);

        $clients = Company::where('is_platform', false)->whereNotNull('billing_customer_id')->get();

        $byClient = $clients->map(function (Company $client) use ($invoices) {
            $clientInvoices = $invoices->where('customer_id', $client->billing_customer_id);
            $invoiced = round((float) $clientInvoices->sum('total_amount'), 2);
            $paid = round((float) $clientInvoices->sum('amount_paid'), 2);

            return [
                'company_id' => $client->id,
                'company_name' => $client->name,
                'client_code' => $client->client_code,
                'status' => $client->status,
                'total_invoiced' => $invoiced,
                'total_paid' => $paid,
                'outstanding_balance' => round($invoiced - $paid, 2),
                'invoice_count' => $clientInvoices->count(),
            ];
        })->sortByDesc('total_invoiced')->values();

        return $this->success([
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'total_outstanding' => round($totalInvoiced - $totalCollected, 2),
            'client_count' => $clients->count(),
            'by_client' => $byClient,
        ]);
    }

    public function storeQuotation(Request $request, Company $client, QuotationService $quotationService): JsonResponse
    {
        $this->assertBillable($client);

        $data = $request->validate($this->lineItemRules() + [
            'valid_until' => ['required', 'date'],
        ]);
        $data['customer_id'] = $client->billing_customer_id;
        $data['branch_id'] = $data['branch_id'] ?? $this->defaultBranchId();

        $quotation = $quotationService->create($data);

        return $this->success(new QuotationResource($quotation->load('items')), 'Quotation created.', 201);
    }

    public function storeInvoice(Request $request, Company $client, InvoiceService $invoiceService): JsonResponse
    {
        $this->assertBillable($client);

        $data = $request->validate($this->lineItemRules() + [
            'due_date' => ['required', 'date'],
        ]);
        $data['customer_id'] = $client->billing_customer_id;
        $data['branch_id'] = $data['branch_id'] ?? $this->defaultBranchId();

        $invoice = $invoiceService->create($data);

        return $this->success(new InvoiceResource($invoice->load('items')), 'Invoice created.', 201);
    }

    protected function lineItemRules(): array
    {
        $companyId = Auth::user()->company_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $companyId)],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function defaultBranchId(): ?int
    {
        return Branch::where('company_id', Auth::user()->company_id)->where('is_main', true)->value('id');
    }

    protected function assertBillable(Company $client): void
    {
        abort_if($client->is_platform, 404);
        abort_if(! $client->billing_customer_id, 422, 'This client has no billing customer set up yet.');
    }
}
