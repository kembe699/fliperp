<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();

        if (! $branch) {
            return;
        }

        $warehouse = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'MAIN-WH'],
            ['branch_id' => $branch->id, 'name' => 'Main Warehouse', 'is_default' => true],
        );

        $units = [
            'PC' => 'Piece',
            'KG' => 'Kilogram',
            'L' => 'Litre',
        ];

        $unitModels = [];
        foreach ($units as $abbreviation => $name) {
            $unitModels[$abbreviation] = UnitOfMeasure::firstOrCreate(
                ['company_id' => $company->id, 'abbreviation' => $abbreviation],
                ['name' => $name],
            );
        }

        $categories = ['Beverages', 'Food', 'Supplies'];

        $categoryModels = [];
        foreach ($categories as $index => $name) {
            $categoryModels[$name] = Category::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['is_active' => true, 'sort_order' => $index],
            );
        }

        $products = [
            [
                'category' => 'Beverages',
                'unit' => 'L',
                'name' => 'Soda 500ml',
                'sku' => 'BEV-SODA-500',
                'cost_price' => 1200,
                'selling_price' => 2000,
                'reorder_level' => 50,
                'track_inventory' => true,
            ],
            [
                'category' => 'Food',
                'unit' => 'KG',
                'name' => 'Rice',
                'sku' => 'FOOD-RICE-1KG',
                'cost_price' => 3500,
                'selling_price' => 4500,
                'reorder_level' => 20,
                'track_inventory' => true,
            ],
            [
                'category' => 'Food',
                'unit' => 'PC',
                'name' => 'Grilled Chicken Plate',
                'sku' => 'FOOD-CHICKEN-PLATE',
                'cost_price' => 6000,
                'selling_price' => 12000,
                'reorder_level' => 0,
                // Made to order, not tracked as countable stock.
                'track_inventory' => false,
            ],
            [
                'category' => 'Supplies',
                'unit' => 'PC',
                'name' => 'Takeaway Container',
                'sku' => 'SUP-CONTAINER',
                'cost_price' => 300,
                'selling_price' => 500,
                'reorder_level' => 100,
                'track_inventory' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['company_id' => $company->id, 'sku' => $product['sku']],
                [
                    'category_id' => $categoryModels[$product['category']]->id,
                    'unit_of_measure_id' => $unitModels[$product['unit']]->id,
                    'name' => $product['name'],
                    'cost_price' => $product['cost_price'],
                    'selling_price' => $product['selling_price'],
                    'reorder_level' => $product['reorder_level'],
                    'is_active' => true,
                    'track_inventory' => $product['track_inventory'],
                ],
            );
        }
    }
}
