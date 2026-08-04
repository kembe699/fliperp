<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated endpoints backing the receipt QR code — a customer scans
 * the code printed on their receipt and lands on a page confirming the sale
 * is genuine, without needing to log in. Sale::find() here is intentionally
 * NOT scoped by TenantModel's CompanyScope (that scope only applies when a
 * user is authenticated, which is never true on a public route) — the sale's
 * own auto-increment id is the only lookup key, so there's no cross-tenant
 * ambiguity to worry about. Only receipt-safe fields are exposed; nothing a
 * customer wouldn't already see printed on the paper itself.
 */
class PublicSaleController extends Controller
{
    public function verify(Sale $sale): JsonResponse
    {
        $company = Company::find($sale->company_id);

        return $this->success([
            'reference_number' => $sale->reference_number,
            'sale_date' => $sale->sale_date?->toDateString(),
            'total_amount' => (float) $sale->total_amount,
            'currency_code' => $company?->currency_code ?? 'USD',
            'status' => $sale->status,
            'company_name' => $company?->name,
        ]);
    }
}
