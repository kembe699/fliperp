<?php

namespace App\Services\Crm;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmService;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Links CRM leads/deals into the existing, unmodified Quotation module.
 *
 * Design decision: a lead must be converted to a customer before it can be
 * quoted (the frontend is told to convert first, with a Convert button
 * offered right in the Quotation tab) rather than quoting against a
 * lightweight "prospective customer" record. Reasoning: a prospective-
 * customer record would need its own reconciliation step when the lead
 * later *does* convert (merge or orphan the earlier quotation's customer?),
 * which is exactly the kind of CRM-specific special case the brief asked to
 * avoid. Requiring conversion first means every quotation this service
 * creates belongs to a real, permanent Customer from the moment it exists.
 */
class CrmQuotationLinkService
{
    public function __construct(protected QuotationService $quotationService) {}

    public function createForDeal(CrmDeal $deal, array $data): Quotation
    {
        if (! $deal->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => ['This deal has no linked customer yet — link it to a customer before creating a quotation.'],
            ]);
        }

        $customer = Customer::findOrFail($deal->customer_id);
        $items = $this->buildItems($deal->services()->get(), $data['items'] ?? null);
        $quotation = $this->createQuotation($customer, $items, $data);

        $deal->quotations()->attach($quotation->id);

        return $quotation;
    }

    public function createForLead(CrmLead $lead, array $data): Quotation
    {
        if ($lead->status !== 'converted' || ! $lead->converted_customer_id) {
            throw ValidationException::withMessages([
                'lead_id' => ['Convert this lead to a customer before creating a quotation.'],
            ]);
        }

        $customer = Customer::findOrFail($lead->converted_customer_id);
        $items = $this->buildItems($lead->services()->get(), $data['items'] ?? null);
        $quotation = $this->createQuotation($customer, $items, $data);

        $lead->quotations()->attach($quotation->id);

        return $quotation;
    }

    /**
     * @param  Collection<int, CrmService>  $attachedServices
     * @param  array<int, array{crm_service_id: int, quantity?: float, unit_price?: float}>|null  $overrideItems
     */
    protected function buildItems(Collection $attachedServices, ?array $overrideItems): array
    {
        if ($overrideItems) {
            return collect($overrideItems)->map(function (array $item) {
                $service = CrmService::findOrFail($item['crm_service_id']);
                $product = $service->ensureProduct();

                return [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? (float) $service->default_price,
                ];
            })->all();
        }

        if ($attachedServices->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Attach at least one interested service first, or provide line items explicitly.'],
            ]);
        }

        return $attachedServices->map(fn (CrmService $service) => [
            'product_id' => $service->ensureProduct()->id,
            'quantity' => 1,
            'unit_price' => (float) $service->default_price,
        ])->all();
    }

    protected function createQuotation(Customer $customer, array $items, array $data): Quotation
    {
        return $this->quotationService->create([
            'branch_id' => $this->resolveBranchId($customer, $data),
            'customer_id' => $customer->id,
            'price_list_id' => $data['price_list_id'] ?? null,
            'valid_until' => $data['valid_until'] ?? now()->addDays(14)->toDateString(),
            'notes' => $data['notes'] ?? null,
            'items' => $items,
        ]);
    }

    /**
     * quotations.branch_id is NOT NULL, but plenty of customers (walk-ins,
     * older records, ones created outside a branch-scoped flow) have no
     * branch_id of their own — falling through to a database constraint
     * violation there produced a 500 instead of a clean error. Falls back
     * to the company's main branch before giving up.
     */
    protected function resolveBranchId(Customer $customer, array $data): int
    {
        $branchId = $data['branch_id'] ?? $customer->branch_id;

        if ($branchId) {
            return $branchId;
        }

        $mainBranch = Branch::query()->where('company_id', $customer->company_id)->where('is_main', true)->first();

        if (! $mainBranch) {
            throw ValidationException::withMessages([
                'branch_id' => ['This customer has no branch on file and no main branch exists to fall back to — set a branch on the customer first.'],
            ]);
        }

        return $mainBranch->id;
    }
}
