<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Procurement\GrnService;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ProcurementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'MAIN-WH')->first();
        $product = Product::where('company_id', $company->id)->where('sku', 'FOOD-RICE-1KG')->first();
        $actor = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_admin'))
            ->first();

        if (! $branch || ! $warehouse || ! $product || ! $actor) {
            return;
        }

        $supplier = Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Kampala Wholesale Foods'],
            [
                'contact_person' => 'Grace Nakato',
                'phone' => '+256700111222',
                'email' => 'sales@kwf.example',
                'payment_terms_days' => 30,
                'is_active' => true,
            ],
        );

        Supplier::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'City Beverage Distributors'],
            [
                'contact_person' => 'Peter Okello',
                'phone' => '+256700333444',
                'email' => 'orders@citybev.example',
                'payment_terms_days' => 14,
                'is_active' => true,
            ],
        );

        if (PurchaseOrder::where('company_id', $company->id)->exists()) {
            return;
        }

        // The PO/GRN services rely on Auth::id() for created_by/received_by
        // and on TenantModel's creating hook for company_id, so we act as a
        // real company_admin while seeding this sample flow.
        Auth::login($actor);

        $purchaseOrderService = app(PurchaseOrderService::class);
        $grnService = app(GrnService::class);

        $purchaseOrder = $purchaseOrderService->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'PO-DEMO-0001',
            'order_date' => now()->subDays(5)->toDateString(),
            'expected_delivery_date' => now()->subDays(1)->toDateString(),
            'notes' => 'Demo purchase order seeded for the demo company.',
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 100, 'unit_cost' => 3000],
            ],
        ]);

        $purchaseOrderService->submit($purchaseOrder);
        $purchaseOrder = $purchaseOrderService->approve($purchaseOrder);

        $grn = $grnService->create([
            'purchase_order_id' => $purchaseOrder->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'reference_number' => 'GRN-DEMO-0001',
            'received_date' => now()->toDateString(),
            'notes' => 'Partial delivery received; the remaining 20kg is still outstanding.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'purchase_order_item_id' => $purchaseOrder->items->first()->id,
                    'quantity_received' => 80,
                    'unit_cost' => 3000,
                    'condition' => 'good',
                ],
            ],
        ]);

        $grnService->confirm($grn);

        Auth::logout();
    }
}
