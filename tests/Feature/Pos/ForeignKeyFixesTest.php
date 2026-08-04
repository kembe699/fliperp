<?php

use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->supplier = createSupplier($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createSupplierBill = function () {
        return SupplierBill::create([
            'company_id' => $this->supplier->company_id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => 'FK-BILL-'.Str::upper(Str::random(6)),
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'status' => 'unpaid',
        ]);
    };
});

it('rejects assigning a non-existent tax_rate_id to a product at the database level', function () {
    $product = createProduct($this->company);

    expect(function () use ($product) {
        DB::transaction(function () use ($product) {
            $product->update(['tax_rate_id' => 999999]);
        });
    })->toThrow(QueryException::class);
});

it('allows assigning a real tax_rate_id to a product and nulls it out when the tax rate is deleted', function () {
    $taxRate = createTaxRate($this->company);
    $product = createProduct($this->company);
    $product->update(['tax_rate_id' => $taxRate->id]);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'tax_rate_id' => $taxRate->id]);

    $taxRate->delete();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'tax_rate_id' => null]);
});

it('rejects assigning a non-existent payment_type_id to a supplier payment at the database level', function () {
    $bill = ($this->createSupplierBill)();

    expect(function () use ($bill) {
        DB::transaction(function () use ($bill) {
            SupplierPayment::create([
                'supplier_id' => $this->supplier->id,
                'supplier_bill_id' => $bill->id,
                'payment_date' => now()->toDateString(),
                'amount' => 10,
                'payment_type_id' => 999999,
                'paid_by' => $this->admin->id,
            ]);
        });
    })->toThrow(QueryException::class);
});

it('blocks deleting a payment type already referenced by a supplier payment', function () {
    $paymentType = createPaymentType($this->company);
    $bill = ($this->createSupplierBill)();

    $this->postJson('/api/v1/supplier-payments', [
        'supplier_id' => $this->supplier->id,
        'supplier_bill_id' => $bill->id,
        'payment_date' => now()->toDateString(),
        'amount' => 50,
        'payment_type_id' => $paymentType->id,
    ])->assertCreated();

    $this->deleteJson("/api/v1/payment-types/{$paymentType->id}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
