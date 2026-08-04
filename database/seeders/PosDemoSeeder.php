<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockMovementService;
use App\Services\Pos\CashDrawerService;
use App\Services\Pos\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'MAIN-WH')->first();
        $soda = Product::where('company_id', $company->id)->where('sku', 'BEV-SODA-500')->first();
        $rice = Product::where('company_id', $company->id)->where('sku', 'FOOD-RICE-1KG')->first();
        $container = Product::where('company_id', $company->id)->where('sku', 'SUP-CONTAINER')->first();
        $cashier = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'cashier'))
            ->first();

        if (! $branch || ! $warehouse || ! $soda || ! $cashier) {
            return;
        }

        if (TaxRate::where('company_id', $company->id)->exists()) {
            return;
        }

        $vat = TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT',
            'rate' => 18,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'Zero-Rated',
            'rate' => 0,
            'is_default' => false,
            'is_active' => true,
        ]);

        $cash = PaymentType::create(['company_id' => $company->id, 'name' => 'Cash', 'type' => 'cash', 'is_active' => true]);
        PaymentType::create(['company_id' => $company->id, 'name' => 'Card', 'type' => 'card', 'is_active' => true]);
        PaymentType::create(['company_id' => $company->id, 'name' => 'Mobile Money', 'type' => 'mobile_money', 'is_active' => true]);
        PaymentType::create(['company_id' => $company->id, 'name' => 'Bank Transfer', 'type' => 'bank_transfer', 'is_active' => true]);
        PaymentType::create(['company_id' => $company->id, 'name' => 'Credit', 'type' => 'credit', 'is_active' => true]);

        $walkIn = Customer::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Walk-in Customer',
            'phone' => '+256700000000',
            'customer_type' => 'walk_in',
            'credit_limit' => 0,
            'is_active' => true,
        ]);

        Customer::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Jane Kintu',
            'phone' => '+256700555666',
            'email' => 'jane.kintu@example.test',
            'customer_type' => 'credit',
            'credit_limit' => 100000,
            'is_active' => true,
        ]);

        // The cash drawer/sale services rely on Auth for company scoping and
        // the acting cashier, so we act as a real cashier while seeding this
        // sample flow.
        Auth::login($cashier);

        app(StockMovementService::class)->record([
            'product_id' => $soda->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => 'purchase',
            'quantity' => 50,
        ]);

        // Every inventory-tracked product sellable from the demo POS grid
        // needs opening stock, or completing a sale that includes it fails
        // the negative-stock guard in StockMovementService.
        if ($rice) {
            app(StockMovementService::class)->record([
                'product_id' => $rice->id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => 'purchase',
                'quantity' => 100,
            ]);
        }

        if ($container) {
            app(StockMovementService::class)->record([
                'product_id' => $container->id,
                'warehouse_id' => $warehouse->id,
                'movement_type' => 'purchase',
                'quantity' => 200,
            ]);
        }

        $cashDrawerService = app(CashDrawerService::class);
        $saleService = app(SaleService::class);

        $cashDrawerService->open(['branch_id' => $branch->id, 'opening_float' => 50000]);

        // A full held -> paid -> completed sale.
        $sale = $saleService->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $walkIn->id,
            'reference_number' => 'SALE-DEMO-0001',
            'sale_type' => 'pos',
            'items' => [
                ['product_id' => $soda->id, 'quantity' => 3, 'unit_price' => (float) $soda->selling_price, 'tax_rate_id' => $vat->id],
            ],
        ]);

        $saleService->addPayment($sale, [
            'payment_type_id' => $cash->id,
            'amount' => (float) $sale->fresh()->total_amount,
        ]);

        $saleService->complete($sale->fresh());

        // A sale that is completed and then voided, to demonstrate the
        // stock/journal reversal.
        $voidedSale = $saleService->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $walkIn->id,
            'reference_number' => 'SALE-DEMO-0002',
            'sale_type' => 'pos',
            'items' => [
                ['product_id' => $soda->id, 'quantity' => 1, 'unit_price' => (float) $soda->selling_price, 'tax_rate_id' => $vat->id],
            ],
        ]);

        $saleService->addPayment($voidedSale, [
            'payment_type_id' => $cash->id,
            'amount' => (float) $voidedSale->fresh()->total_amount,
        ]);

        $voidedSale = $saleService->complete($voidedSale->fresh());
        $saleService->void($voidedSale);

        Auth::logout();
    }
}
