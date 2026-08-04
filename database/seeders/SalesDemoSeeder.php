<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\QuotationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class SalesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();
        $rice = Product::where('company_id', $company->id)->where('sku', 'FOOD-RICE-1KG')->first();
        $creditCustomer = Customer::where('company_id', $company->id)->where('customer_type', 'credit')->first();
        $vat = TaxRate::where('company_id', $company->id)->where('is_default', true)->first();
        $cash = PaymentType::where('company_id', $company->id)->where('type', 'cash')->first();
        $actor = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_admin'))
            ->first();

        if (! $branch || ! $rice || ! $creditCustomer || ! $vat || ! $cash || ! $actor) {
            return;
        }

        if (PriceList::where('company_id', $company->id)->exists()) {
            return;
        }

        $priceList = PriceList::create([
            'company_id' => $company->id,
            'name' => 'Wholesale Price List',
            'currency_code' => 'USD',
            'is_default' => true,
            'is_active' => true,
        ]);

        $priceList->items()->create([
            'product_id' => $rice->id,
            'price' => (float) $rice->selling_price - 500,
        ]);

        Promotion::create([
            'company_id' => $company->id,
            'name' => 'Bulk Rice Discount',
            'type' => 'percentage_discount',
            'value' => 5,
            'applies_to' => 'all_products',
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);

        // The quotation/invoice/payment services rely on Auth for
        // company scoping and the acting user, so we act as a real
        // company_admin while seeding this sample flow.
        Auth::login($actor);

        $quotationService = app(QuotationService::class);
        $invoiceService = app(InvoiceService::class);
        $customerPaymentService = app(CustomerPaymentService::class);

        $quotation = $quotationService->create([
            'branch_id' => $branch->id,
            'customer_id' => $creditCustomer->id,
            'price_list_id' => $priceList->id,
            'reference_number' => 'QUO-DEMO-0001',
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => 'Demo quotation seeded for the demo company.',
            'items' => [
                ['product_id' => $rice->id, 'quantity' => 20, 'tax_rate_id' => $vat->id],
            ],
        ]);

        $quotationService->send($quotation);
        $quotationService->accept($quotation->fresh());
        $invoice = $quotationService->convertToInvoice($quotation->fresh());

        $invoice = $invoiceService->send($invoice);

        $customerPaymentService->create([
            'customer_id' => $creditCustomer->id,
            'invoice_id' => $invoice->id,
            'payment_type_id' => $cash->id,
            'payment_date' => now()->toDateString(),
            'amount' => (float) $invoice->total_amount,
        ]);

        Auth::logout();
    }
}
