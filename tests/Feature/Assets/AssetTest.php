<?php

use App\Models\AssetCategory;
use App\Models\JournalEntryLine;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->accounts = seedChartOfAccounts($this->company);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('assigns and disposes an asset', function () {
    $category = AssetCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Laptops',
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 5,
    ]);

    $assetId = $this->postJson('/api/v1/assets', [
        'branch_id' => $this->branch->id,
        'asset_category_id' => $category->id,
        'asset_code' => 'AST-001',
        'name' => 'Dell Laptop',
        'purchase_date' => now()->subYear()->toDateString(),
        'purchase_cost' => 3000,
    ])->json('data.id');

    $this->postJson("/api/v1/assets/{$assetId}/assign", ['employee_id' => $this->employee->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_to_employee_id', $this->employee->id)
        ->assertJsonPath('data.status', 'in_use');

    $this->postJson("/api/v1/assets/{$assetId}/dispose")
        ->assertOk()
        ->assertJsonPath('data.status', 'disposed')
        ->assertJsonPath('data.assigned_to_employee_id', null);

    $this->assertDatabaseHas('assets', ['id' => $assetId, 'status' => 'disposed', 'assigned_to_employee_id' => null]);
});

it('calculates straight-line and reducing-balance depreciation and posts one balanced journal entry', function () {
    $straightLineCategory = AssetCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Straight Line Equipment',
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 10,
    ]);

    $reducingBalanceCategory = AssetCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Reducing Balance Equipment',
        'depreciation_method' => 'reducing_balance',
        'useful_life_years' => 5,
    ]);

    $slAssetId = $this->postJson('/api/v1/assets', [
        'branch_id' => $this->branch->id,
        'asset_category_id' => $straightLineCategory->id,
        'asset_code' => 'AST-SL-1',
        'name' => 'Straight Line Asset',
        'purchase_date' => now()->subYear()->toDateString(),
        'purchase_cost' => 1200000,
    ])->json('data.id');

    $rbAssetId = $this->postJson('/api/v1/assets', [
        'branch_id' => $this->branch->id,
        'asset_category_id' => $reducingBalanceCategory->id,
        'asset_code' => 'AST-RB-1',
        'name' => 'Reducing Balance Asset',
        'purchase_date' => now()->subYear()->toDateString(),
        'purchase_cost' => 600000,
    ])->json('data.id');

    $response = $this->postJson('/api/v1/assets/run-depreciation');

    $response->assertOk()->assertJsonPath('data.schedules_created', 2);

    // Straight line: 1,200,000 / 10 years / 12 months = 10,000
    $this->assertDatabaseHas('asset_depreciation_schedules', [
        'asset_id' => $slAssetId,
        'depreciation_amount' => 10000,
        'accumulated_depreciation' => 10000,
        'book_value' => 1190000,
    ]);

    // Reducing balance: book value 600,000 * ((2/5)/12) = 20,000
    $this->assertDatabaseHas('asset_depreciation_schedules', [
        'asset_id' => $rbAssetId,
        'depreciation_amount' => 20000,
        'accumulated_depreciation' => 20000,
        'book_value' => 580000,
    ]);

    $journalEntryId = $response->json('data.journal_entry_id');
    expect($journalEntryId)->not->toBeNull();

    $totalDebit = JournalEntryLine::where('journal_entry_id', $journalEntryId)->sum('debit');
    $totalCredit = JournalEntryLine::where('journal_entry_id', $journalEntryId)->sum('credit');
    expect((float) $totalDebit)->toBe((float) $totalCredit)->toBe(30000.0);

    $this->assertDatabaseHas('assets', ['id' => $slAssetId, 'current_value' => 1190000]);
    $this->assertDatabaseHas('assets', ['id' => $rbAssetId, 'current_value' => 580000]);
});
