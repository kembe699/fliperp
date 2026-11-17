<?php

use App\Models\AuditLog;
use App\Models\Donor;
use App\Models\ExchangeRate;
use App\Models\Grant;
use App\Models\JournalEntry;
use App\Models\PaymentRequest;
use App\Models\Role;
use App\Models\SupplierBill;
use App\Notifications\PaymentRequestApprovalNeeded;
use App\Notifications\PaymentRequestDecided;
use App\Notifications\PaymentRequestReadyToPay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// G2: payment requests approved in steps by amount, then paid by Finance.

/** A company role with exactly these permissions (G711-style custom roles). */
function companyRole($company, string $name, array $permissions): Role
{
    $role = Role::create(['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id]);
    $role->givePermissionTo($permissions);

    return $role;
}

function userWith($company, $branch, Role $role, string $name)
{
    $user = createUserWithRole($role->name, $company, $branch);
    $user->update(['name' => $name]);

    return $user->fresh();
}

beforeEach(function () {
    Notification::fake();
    [$this->company, $this->branch] = createCompanyWithMainBranch(['allow_self_approval' => true]);
    $this->accounts = seedChartOfAccounts($this->company);

    $this->requesterRole = companyRole($this->company, 'Data entry', ['payment-requests.view', 'payment-requests.create', 'payment-requests.update', 'payment-requests.submit', 'payment-requests.cancel']);
    $this->poRole = companyRole($this->company, 'Program Officer', ['payment-requests.view', 'payment-requests.approve']);
    $this->edRole = companyRole($this->company, 'Executive Director', ['payment-requests.view', 'payment-requests.approve', 'payment-approval-rules.view', 'payment-approval-rules.manage']);
    $this->financeRole = companyRole($this->company, 'Finance Officer', ['payment-requests.view', 'payment-requests.pay']);
    $this->viewRole = companyRole($this->company, 'View only', ['payment-requests.view', 'payment-approval-rules.view']);

    $this->requester = userWith($this->company, $this->branch, $this->requesterRole, 'Ruth Requester');
    $this->po = userWith($this->company, $this->branch, $this->poRole, 'Paul Officer');
    $this->ed = userWith($this->company, $this->branch, $this->edRole, 'Edna Director');
    $this->finance = userWith($this->company, $this->branch, $this->financeRole, 'Fiona Finance');
    $this->viewer = userWith($this->company, $this->branch, $this->viewRole, 'Victor Viewer');

    // Up to USD 500: Program Officer. Above USD 500: Program Officer, then Executive Director.
    Sanctum::actingAs($this->ed, ['*']);
    $this->postJson('/api/v1/payment-approval-rules', [
        'name' => 'Up to 500', 'min_amount' => 0, 'max_amount' => 500,
        'steps' => [['label' => 'Program Officer', 'role_id' => $this->poRole->id]],
    ])->assertCreated();
    $this->postJson('/api/v1/payment-approval-rules', [
        'name' => 'Above 500', 'min_amount' => 500, 'max_amount' => null,
        'steps' => [['label' => 'Program Officer', 'role_id' => $this->poRole->id], ['label' => 'Executive Director', 'role_id' => $this->edRole->id]],
    ])->assertCreated();

    ExchangeRate::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'currency_code' => 'SSP', 'rate' => 5000, 'effective_date' => '2026-01-01']);
    $this->donor = Donor::create(['company_id' => $this->company->id, 'name' => 'Donor A']);
    $this->grant = Grant::create(['company_id' => $this->company->id, 'donor_id' => $this->donor->id, 'code' => 'SOF-1', 'name' => 'Youth', 'currency_code' => 'USD', 'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'status' => 'active']);

    $this->as = function ($user) {
        Sanctum::actingAs($user, ['*']);

        return $this;
    };
    $this->raise = function (array $overrides = []) {
        Sanctum::actingAs($this->requester, ['*']);

        return $this->postJson('/api/v1/payment-requests', $overrides + [
            'request_date' => '2026-11-17',
            'payee_name' => 'Juba Office Rentals',
            'description' => 'November office rent',
            'amount' => 400,
            'currency_code' => 'USD',
            'expense_account_id' => $this->accounts['5200']->id,
            'grant_id' => $this->grant->id,
        ])->assertCreated()->json('data');
    };
});

it('creates a draft with a yearly reference and the base-currency equivalent', function () {
    $pr = ($this->raise)(['amount' => 2500000, 'currency_code' => 'SSP']);

    expect($pr['reference_number'])->toBe('PR-2026-0001')
        ->and($pr['status'])->toBe('draft')
        ->and($pr['base_amount'])->toEqual(500) // 2,500,000 SSP at 5,000 per USD
        ->and($pr['requester_name'])->toBe('Ruth Requester');
    expect(($this->raise)()['reference_number'])->toBe('PR-2026-0002');
});

it('routes up to USD 500 to one approver and above it to two, in order', function () {
    $small = ($this->raise)(['amount' => 500]);
    $this->postJson("/api/v1/payment-requests/{$small['id']}/submit")->assertOk();
    $small = PaymentRequest::with('approvals')->find($small['id']);
    expect($small->approvals->pluck('label')->all())->toBe(['Program Officer']);

    $large = ($this->raise)(['amount' => 500.01]);
    $this->postJson("/api/v1/payment-requests/{$large['id']}/submit")->assertOk();
    $large = PaymentRequest::with('approvals')->find($large['id']);
    expect($large->approvals->sortBy('sequence')->pluck('label')->values()->all())->toBe(['Program Officer', 'Executive Director']);

    // The ED can't jump the queue: step 1 belongs to the Program Officer.
    ($this->as)($this->ed)->postJson("/api/v1/payment-requests/{$large->id}/approve")->assertStatus(422)->assertJsonPath('errors.approval.0', 'This step is for Program Officer.');

    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$large->id}/approve", ['comment' => 'Budget available'])->assertOk()->assertJsonPath('data.status', 'partially_approved');
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$large->id}/approve")->assertStatus(422); // step 2 is not theirs
    ($this->as)($this->ed)->postJson("/api/v1/payment-requests/{$large->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
});

it('uses the SSP amount converted to USD to pick the rule', function () {
    $pr = ($this->raise)(['amount' => 3000000, 'currency_code' => 'SSP']); // = USD 600
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();

    expect(PaymentRequest::find($pr['id'])->approvals()->count())->toBe(2);
});

it('refuses to submit an amount no rule covers', function () {
    ($this->as)($this->ed)->putJson('/api/v1/payment-approval-rules/'.\App\Models\PaymentApprovalRule::where('name', 'Above 500')->value('id'), ['is_active' => false])->assertOk();
    $pr = ($this->raise)(['amount' => 900]);

    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertStatus(422)->assertJsonValidationErrors('amount');
    expect(PaymentRequest::find($pr['id'])->status)->toBe('draft');
});

it('returns a rejected request to the requester, who edits and resubmits it as a new round', function () {
    $pr = ($this->raise)(['amount' => 800]);
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();

    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/reject")->assertStatus(422)->assertJsonValidationErrors('comment');
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/reject", ['comment' => 'Attach the quote'])->assertOk()->assertJsonPath('data.status', 'rejected');
    Notification::assertSentTo($this->requester, PaymentRequestDecided::class, fn ($n) => $n->decision === 'rejected' && $n->comment === 'Attach the quote');

    // Nobody can approve a rejected request.
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertStatus(422);

    ($this->as)($this->requester)->putJson("/api/v1/payment-requests/{$pr['id']}", ['amount' => 450])->assertOk();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk()->assertJsonPath('data.approval_round', 2);

    $request = PaymentRequest::with('approvals', 'events')->find($pr['id']);
    expect($request->approvals->where('round', 1)->pluck('status')->sort()->values()->all())->toBe(['cancelled', 'rejected'])
        ->and($request->approvals->where('round', 2)->pluck('label')->all())->toBe(['Program Officer']) // now under 500
        ->and($request->events->pluck('action')->all())->toBe(['created', 'submitted', 'rejected', 'edited', 'resubmitted']);
});

it('never lets the requester approve their own request, even with self-approval allowed', function () {
    // The requester also holds the approver role; the company allows self-approval.
    $this->requester->assignRole($this->poRole);
    $other = userWith($this->company, $this->branch, $this->poRole, 'Other Officer');
    $pr = ($this->raise)();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();

    ($this->as)($this->requester)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")
        ->assertForbidden()->assertJsonPath('message', 'You raised this payment request, so someone else must approve it.');
    ($this->as)($other)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
});

it('refuses to submit when only the requester could approve a step', function () {
    $this->po->removeRole($this->poRole);
    $this->requester->assignRole($this->poRole);
    $pr = ($this->raise)();

    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertStatus(422)->assertJsonValidationErrors('approval');
});

it('needs a different person for each step', function () {
    // One person holds both roles and is the only Executive Director.
    $this->po->assignRole($this->edRole);
    $this->ed->removeRole($this->edRole);
    $pr = ($this->raise)(['amount' => 900]);
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertStatus(422)->assertJsonValidationErrors('approval');

    // With a second approver available, the first can't take both steps.
    $this->ed->assignRole($this->edRole);
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertStatus(422)
        ->assertJsonPath('errors.approval.0', 'You already approved an earlier step of this request; a different approver must approve this one.');
});

it('blocks payment until fully approved, then posts the payment with its coding', function () {
    $pr = ($this->raise)(['amount' => 900]);
    ($this->as)($this->finance)->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertStatus(422); // draft

    ($this->as)($this->requester)->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    ($this->as)($this->finance)->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertStatus(422); // partly approved
    ($this->as)($this->ed)->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertForbidden(); // no pay permission
    ($this->as)($this->ed)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();

    $paid = ($this->as)($this->finance)->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18', 'reference' => 'CHQ 1042'])
        ->assertOk()->json('data');

    expect($paid['status'])->toBe('paid')
        ->and($paid['payment_reference'])->toBe('CHQ 1042')
        ->and($paid['journal_entry_reference'])->toBe('PR-2026-0001-PAY')
        ->and($paid['payment_account'])->toBe('1000 Cash and Bank');

    $entry = JournalEntry::with('lines.account')->find($paid['journal_entry_id']);
    $debit = $entry->lines->firstWhere('debit', '>', 0);
    $credit = $entry->lines->firstWhere('credit', '>', 0);
    expect($entry->status)->toBe('posted')
        ->and($entry->source_module)->toBe('payment_request')
        ->and($debit->account->code)->toBe('5200')
        ->and((float) $debit->debit)->toBe(900.0)
        ->and($debit->grant_id)->toBe($this->grant->id)
        ->and($credit->account->code)->toBe('1000');

    // Paid once only.
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertStatus(422);
});

it('settles a linked supplier bill through the supplier payment', function () {
    $supplier = createSupplier($this->company, ['name' => 'Nile Stationers']);
    ($this->as)(createUserWithRole('company_admin', $this->company, $this->branch));
    $bill = $this->postJson('/api/v1/supplier-bills', [
        'supplier_id' => $supplier->id, 'reference_number' => 'INV-77', 'bill_date' => '2026-11-10', 'due_date' => '2026-12-10', 'subtotal' => 300, 'tax_amount' => 0,
    ])->assertCreated()->json('data');

    $pr = ($this->raise)(['payee_name' => null, 'supplier_bill_id' => $bill['id'], 'amount' => 300, 'expense_account_id' => null, 'grant_id' => null]);
    expect($pr['supplier_name'])->toBe('Nile Stationers');
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    $paid = ($this->as)($this->finance)->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertOk()->json('data');

    expect($paid['supplier_payment_id'])->not->toBeNull()
        ->and(SupplierBill::find($bill['id'])->status)->toBe('paid');
});

it('refuses a request bigger than what the linked bill still owes', function () {
    $supplier = createSupplier($this->company);
    ($this->as)(createUserWithRole('company_admin', $this->company, $this->branch));
    $bill = $this->postJson('/api/v1/supplier-bills', ['supplier_id' => $supplier->id, 'reference_number' => 'INV-78', 'bill_date' => '2026-11-10', 'due_date' => '2026-12-10', 'subtotal' => 100, 'tax_amount' => 0])->json('data');

    $pr = ($this->raise)(['payee_name' => null, 'supplier_bill_id' => $bill['id'], 'amount' => 150, 'grant_id' => null]);
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertStatus(422)->assertJsonValidationErrors('amount');
});

it('enforces each role\'s permissions on the server', function () {
    $pr = ($this->raise)();

    // View only: can list and open, nothing else.
    ($this->as)($this->viewer)->getJson('/api/v1/payment-requests')->assertOk();
    $this->getJson("/api/v1/payment-requests/{$pr['id']}")->assertOk()->assertJsonPath('data.can.submit', false);
    $this->postJson('/api/v1/payment-requests', ['request_date' => '2026-11-17', 'payee_name' => 'X', 'description' => 'Y', 'amount' => 1])->assertForbidden();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertForbidden();
    $this->putJson("/api/v1/payment-requests/{$pr['id']}", ['amount' => 1])->assertForbidden();
    $this->postJson('/api/v1/payment-approval-rules', ['name' => 'x', 'min_amount' => 0, 'steps' => [['label' => 'x', 'role_id' => $this->poRole->id]]])->assertForbidden();

    // Approver: cannot create, cannot pay.
    ($this->as)($this->po)->postJson('/api/v1/payment-requests', ['request_date' => '2026-11-17', 'payee_name' => 'X', 'description' => 'Y', 'amount' => 1])->assertForbidden();

    // Finance: cannot approve.
    ($this->as)($this->requester)->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    ($this->as)($this->finance)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertForbidden();

    // Only an admin can change the rules.
    ($this->as)($this->requester)->getJson('/api/v1/payment-approval-rules')->assertForbidden();
});

it('records the timeline and the audit log for every move', function () {
    $pr = ($this->raise)();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit", ['comment' => 'Urgent'])->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve", ['comment' => 'OK'])->assertOk();

    $events = ($this->as)($this->viewer)->getJson("/api/v1/payment-requests/{$pr['id']}")->json('data.events');
    expect(collect($events)->map(fn ($e) => [$e['action'], $e['user_name']])->all())->toBe([
        ['created', 'Ruth Requester'], ['submitted', 'Ruth Requester'], ['approved', 'Paul Officer'],
    ])->and($events[2]['comment'])->toBe('Step 1: Program Officer – OK');

    $audit = AuditLog::query()->where('module', 'PaymentRequest')->where('record_id', $pr['id'])->orderBy('id')->get();
    expect($audit->pluck('action')->first())->toBe('created')
        ->and($audit->where('action', 'updated')->count())->toBeGreaterThanOrEqual(2)
        ->and($audit->pluck('user_id')->unique()->values()->all())->toContain($this->po->id);
});

it('notifies the next approver, the requester and Finance', function () {
    $pr = ($this->raise)(['amount' => 900]);
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    Notification::assertSentTo($this->po, PaymentRequestApprovalNeeded::class);
    Notification::assertNotSentTo($this->ed, PaymentRequestApprovalNeeded::class);

    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    Notification::assertSentTo($this->ed, PaymentRequestApprovalNeeded::class);

    ($this->as)($this->ed)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertOk();
    Notification::assertSentTo($this->requester, PaymentRequestDecided::class, fn ($n) => $n->decision === 'approved');
    Notification::assertSentTo($this->finance, PaymentRequestReadyToPay::class);
    Notification::assertSentTo($this->finance, PaymentRequestReadyToPay::class, fn ($n, $channels) => in_array('mail', $channels, true) && in_array('database', $channels, true));
});

it('lists my requests and the ones waiting for my approval', function () {
    $a = ($this->raise)(['amount' => 100]);
    $b = ($this->raise)(['amount' => 900]);
    $this->postJson("/api/v1/payment-requests/{$a['id']}/submit")->assertOk();
    $this->postJson("/api/v1/payment-requests/{$b['id']}/submit")->assertOk();

    expect(collect(($this->as)($this->po)->getJson('/api/v1/payment-requests?tab=awaiting')->json('data'))->pluck('id')->sort()->values()->all())->toBe([$a['id'], $b['id']]);
    expect(($this->as)($this->ed)->getJson('/api/v1/payment-requests?tab=awaiting')->json('data'))->toBe([]); // step 1 first

    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$b['id']}/approve")->assertOk();
    expect(collect(($this->as)($this->ed)->getJson('/api/v1/payment-requests?tab=awaiting')->json('data'))->pluck('id')->all())->toBe([$b['id']]);
    expect(collect(($this->as)($this->requester)->getJson('/api/v1/payment-requests?tab=mine')->json('data'))->count())->toBe(2);
});

it('keeps each company\'s requests and rules to itself', function () {
    $pr = ($this->raise)();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();

    [$other, $otherBranch] = createCompanyWithMainBranch();
    $outsider = createUserWithRole('company_admin', $other, $otherBranch);
    ($this->as)($outsider);

    $this->getJson("/api/v1/payment-requests/{$pr['id']}")->assertNotFound();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertNotFound();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/pay", ['payment_date' => '2026-11-18'])->assertNotFound();
    expect($this->getJson('/api/v1/payment-requests')->json('data'))->toBe([])
        ->and($this->getJson('/api/v1/payment-approval-rules')->json('data.rules'))->toBe([]);

    // Their rule can't name our role or our user.
    $this->postJson('/api/v1/payment-approval-rules', ['name' => 'x', 'min_amount' => 0, 'steps' => [['label' => 'x', 'role_id' => $this->poRole->id]]])->assertStatus(422);
    $this->postJson('/api/v1/payment-approval-rules', ['name' => 'x', 'min_amount' => 0, 'steps' => [['label' => 'x', 'user_id' => $this->po->id]]])->assertStatus(422);
});

it('rejects overlapping rules and steps without an approver', function () {
    ($this->as)($this->ed);
    $this->postJson('/api/v1/payment-approval-rules', ['name' => 'Overlap', 'min_amount' => 400, 'max_amount' => 700, 'steps' => [['label' => 'PO', 'role_id' => $this->poRole->id]]])
        ->assertStatus(422)->assertJsonValidationErrors('min_amount');
    $this->postJson('/api/v1/payment-approval-rules', ['name' => 'No approver', 'min_amount' => 0, 'max_amount' => 10, 'is_active' => false, 'steps' => [['label' => 'Nobody']]])
        ->assertStatus(422)->assertJsonValidationErrors('steps.0.role_id');
});

it('stores supporting documents and prints a voucher with the approvals', function () {
    Storage::fake('local');
    $pr = ($this->raise)();
    $this->post("/api/v1/payment-requests/{$pr['id']}/attachments", ['attachments' => [UploadedFile::fake()->create('quote.pdf', 120, 'application/pdf')]])
        ->assertOk()->assertJsonPath('data.attachments.0.file_name', 'quote.pdf');
    $attachment = PaymentRequest::find($pr['id'])->attachments()->first();
    Storage::disk('local')->assertExists($attachment->file_path); // private disk, not public
    $this->get("/api/v1/payment-requests/{$pr['id']}/attachments/{$attachment->id}")->assertOk();
    ($this->as)(createUserWithRole('company_admin', ...createCompanyWithMainBranch()))->get("/api/v1/payment-requests/{$pr['id']}/attachments/{$attachment->id}")->assertNotFound();
    ($this->as)($this->requester);

    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve", ['comment' => 'Fine'])->assertOk();

    $pdf = $this->get("/api/v1/payment-requests/{$pr['id']}/pdf")->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf');
});

it('only allows the documented status moves', function () {
    $pr = ($this->raise)();
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertStatus(422); // draft can't be approved
    ($this->as)($this->requester)->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertOk();
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/submit")->assertStatus(422); // already submitted
    $this->putJson("/api/v1/payment-requests/{$pr['id']}", ['amount' => 1])->assertStatus(422); // can't edit while in approval
    $this->postJson("/api/v1/payment-requests/{$pr['id']}/cancel", ['comment' => 'Not needed'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    ($this->as)($this->po)->postJson("/api/v1/payment-requests/{$pr['id']}/approve")->assertStatus(422); // cancelled
    expect(PaymentRequest::find($pr['id'])->approvals()->pluck('status')->all())->toBe(['cancelled']);
});
