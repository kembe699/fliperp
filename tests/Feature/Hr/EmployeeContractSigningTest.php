<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->employee = createEmployee($this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('creates a draft contract with a server-generated template body pre-filled from employee data', function () {
    $response = $this->postJson('/api/v1/employee-contracts', [
        'employee_id' => $this->employee->id,
        'contract_type' => 'Permanent',
        'start_date' => '2026-01-01',
        'base_salary' => 500000,
        'currency_code' => 'USD',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.document_url', null);

    expect($response->json('data.contract_body'))
        ->toContain($this->employee->first_name)
        ->toContain('500,000.00');
});

it('enforces the draft -> generated -> signed status transition order', function () {
    $contractId = $this->postJson('/api/v1/employee-contracts', [
        'employee_id' => $this->employee->id,
        'contract_type' => 'Permanent',
        'start_date' => '2026-01-01',
        'base_salary' => 500000,
    ])->json('data.id');

    // Cannot skip straight from draft to signed via the update endpoint.
    $this->putJson("/api/v1/employee-contracts/{$contractId}", ['status' => 'signed'])
        ->assertStatus(422);

    // draft -> generated is allowed.
    $this->putJson("/api/v1/employee-contracts/{$contractId}", [
        'status' => 'generated',
        'contract_body' => '<p>HR-edited contract text.</p>',
    ])->assertOk()->assertJsonPath('data.status', 'generated');

    // generated -> draft (backslide) is rejected.
    $this->putJson("/api/v1/employee-contracts/{$contractId}", ['status' => 'draft'])
        ->assertStatus(422);

    // Signing is only reachable through the dedicated /sign endpoint.
    $signResponse = $this->postJson("/api/v1/employee-contracts/{$contractId}/sign", [
        'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'signed_by_name' => 'Jane Doe',
    ]);

    $signResponse->assertOk()
        ->assertJsonPath('data.status', 'signed')
        ->assertJsonPath('data.signed_by_name', 'Jane Doe');

    expect($signResponse->json('data.document_url'))->not->toBeNull();
    expect($signResponse->json('data.signed_at'))->not->toBeNull();
});

it('generates a real readable PDF once signed and rejects signing an already-signed contract', function () {
    $contractId = $this->postJson('/api/v1/employee-contracts', [
        'employee_id' => $this->employee->id,
        'contract_type' => 'Permanent',
        'start_date' => '2026-01-01',
        'base_salary' => 500000,
    ])->json('data.id');

    $signaturePng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    $this->postJson("/api/v1/employee-contracts/{$contractId}/sign", [
        'signature_data' => $signaturePng,
        'signed_by_name' => 'Jane Doe',
    ])->assertOk();

    // Re-signing an already-signed contract is rejected.
    $this->postJson("/api/v1/employee-contracts/{$contractId}/sign", [
        'signature_data' => $signaturePng,
        'signed_by_name' => 'Someone Else',
    ])->assertStatus(422);

    $pdfResponse = $this->get("/api/v1/employee-contracts/{$contractId}/pdf");
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('Content-Type'))->toContain('application/pdf');
    expect(substr($pdfResponse->getContent(), 0, 4))->toBe('%PDF');
});

it('uploads a signed physical copy directly, bypassing the generate/sign flow', function () {
    Illuminate\Support\Facades\Storage::fake('public');

    $contractId = $this->postJson('/api/v1/employee-contracts', [
        'employee_id' => $this->employee->id,
        'contract_type' => 'Fixed-term',
        'start_date' => '2026-01-01',
        'base_salary' => 300000,
    ])->json('data.id');

    $file = Illuminate\Http\UploadedFile::fake()->create('signed-contract.pdf', 200, 'application/pdf');

    $response = $this->postJson("/api/v1/employee-contracts/{$contractId}/upload-document", [
        'document' => $file,
        'is_signed_physical_copy' => true,
        'signed_by_name' => 'John Smith',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed')
        ->assertJsonPath('data.signed_by_name', 'John Smith');

    expect($response->json('data.document_url'))->toContain('/storage/employee-contracts/');
});

it('denies signing a contract belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherEmployee = createEmployee($otherCompany, $otherBranch);
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);

    Sanctum::actingAs($otherAdmin, ['*']);
    $otherContractId = $this->postJson('/api/v1/employee-contracts', [
        'employee_id' => $otherEmployee->id,
        'contract_type' => 'Permanent',
        'start_date' => '2026-01-01',
        'base_salary' => 500000,
    ])->json('data.id');

    Sanctum::actingAs($this->admin, ['*']);

    // EmployeeContract isn't a TenantModel (no automatic global-scope 404 on
    // route binding) — cross-company denial happens at the Policy level, so
    // this is a 403, not a 404.
    $this->postJson("/api/v1/employee-contracts/{$otherContractId}/sign", [
        'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'signed_by_name' => 'Someone',
    ])->assertStatus(403);
});
