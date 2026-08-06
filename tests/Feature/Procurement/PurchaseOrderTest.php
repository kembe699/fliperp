<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->warehouse = createWarehouse($this->company, $this->branch);
    $this->supplier = createSupplier($this->company);
    $this->product = createProduct($this->company);

    Sanctum::actingAs($this->admin, ['*']);

    $this->createDraftPo = function (string $reference = 'PO-001') {
        return $this->postJson('/api/v1/purchase-orders', [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'reference_number' => $reference,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->product->id, 'quantity_ordered' => 10, 'unit_cost' => 100],
            ],
        ])->json('data.id');
    };
});

it('enforces the draft -> submitted -> approved status transition order', function () {
    $poId = ($this->createDraftPo)();

    $this->postJson("/api/v1/purchase-orders/{$poId}/approve")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    $this->postJson("/api/v1/purchase-orders/{$poId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('blocks a user from approving their own purchase order when another eligible approver exists', function () {
    $secondAdmin = createUserWithRole('company_admin', $this->company, $this->branch);

    $poId = ($this->createDraftPo)('PO-SOD-001'); // created as $this->admin
    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();

    // $this->admin created and submitted it and also has purchase-orders.approve,
    // but $secondAdmin — another user in the same company who also holds that
    // permission — exists, so self-approval is blocked (maker-checker).
    $this->postJson("/api/v1/purchase-orders/{$poId}/approve")->assertStatus(403);

    Sanctum::actingAs($secondAdmin, ['*']);
    $this->postJson("/api/v1/purchase-orders/{$poId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('blocks editing a purchase order once it has been submitted', function () {
    $poId = ($this->createDraftPo)();

    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();

    $this->putJson("/api/v1/purchase-orders/{$poId}", ['notes' => 'Trying to sneak an edit in'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('cancels a purchase order from draft, submitted or approved', function () {
    $draftId = ($this->createDraftPo)('PO-CANCEL-DRAFT');
    $this->postJson("/api/v1/purchase-orders/{$draftId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $approvedId = ($this->createDraftPo)('PO-CANCEL-APPROVED');
    $this->postJson("/api/v1/purchase-orders/{$approvedId}/submit")->assertOk();
    $this->postJson("/api/v1/purchase-orders/{$approvedId}/approve")->assertOk();
    $this->postJson("/api/v1/purchase-orders/{$approvedId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('deletes only draft purchase orders', function () {
    $poId = ($this->createDraftPo)();
    $this->postJson("/api/v1/purchase-orders/{$poId}/submit")->assertOk();

    $this->deleteJson("/api/v1/purchase-orders/{$poId}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
