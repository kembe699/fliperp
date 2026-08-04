<?php

use App\Models\StockLevel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);
});

it('rejects a duplicate no-variant stock_levels row at the database level', function () {
    StockLevel::create([
        'product_id' => $this->product->id,
        'product_variant_id' => null,
        'warehouse_id' => $this->warehouse->id,
        'quantity_on_hand' => 10,
    ]);

    // Wrapped in its own transaction (a savepoint, since RefreshDatabase already
    // has one open) so the expected failure doesn't poison the outer test
    // transaction and block the assertions below.
    expect(function () {
        DB::transaction(function () {
            StockLevel::create([
                'product_id' => $this->product->id,
                'product_variant_id' => null,
                'warehouse_id' => $this->warehouse->id,
                'quantity_on_hand' => 5,
            ]);
        });
    })->toThrow(QueryException::class);

    expect(StockLevel::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->count())->toBe(1);
});
