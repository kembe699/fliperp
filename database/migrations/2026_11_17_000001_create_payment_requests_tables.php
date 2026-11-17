<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment requests and their approval workflow.
 *
 * - payment_approval_rules: per company, an amount band in the base currency
 *   ("above min_amount, up to and including max_amount"; no max = no upper
 *   limit) and, in payment_approval_rule_steps, who approves it, in order.
 * - payment_requests: the request itself. Approval steps are copied from the
 *   matching rule into payment_request_approvals when it is submitted, so
 *   later rule changes never alter a request already in flight. A rejected
 *   and resubmitted request gets a new round of steps; earlier rounds stay
 *   as history.
 * - payment_request_events: the timeline (who did what, when, with what
 *   comment), on top of the generic audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('payment_approval_rule_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_approval_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('label');
            // Exactly one of the two: anyone holding the role, or one named user.
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payment_approval_rule_id', 'sequence']);
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->date('request_date');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            // Who is paid: a supplier on file, or a free-text payee.
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payee_name')->nullable();
            $table->text('description');

            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 24, 10)->default(1);
            // amount in the base currency at the request date's rate; picks the approval rule.
            $table->decimal('base_amount', 18, 2);

            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('grant_id')->nullable()->constrained('grants')->restrictOnDelete();
            $table->foreignId('dea_id')->nullable()->constrained('grant_deas')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('me_projects')->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->restrictOnDelete();

            // Optional links to the procurement trail.
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained()->restrictOnDelete();

            // draft → submitted → partially_approved → approved → paid; or rejected / cancelled.
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('approval_round')->default(0);
            $table->foreignId('payment_approval_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Payment. payment_account_id records the account the money left from
            // (today always the cash/bank account 1000); a later "pay from" choice
            // only has to fill it from the request instead.
            $table->date('payment_date')->nullable();
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
            $table->index(['company_id', 'status']);
            $table->index('requested_by');
        });

        Schema::create('payment_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->unsignedSmallInteger('sequence');
            $table->string('label');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // pending → approved | rejected; cancelled when the request is withdrawn mid-way.
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['payment_request_id', 'round', 'sequence']);
        });

        Schema::create('payment_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_request_attachments');
        Schema::dropIfExists('payment_request_events');
        Schema::dropIfExists('payment_request_approvals');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('payment_approval_rule_steps');
        Schema::dropIfExists('payment_approval_rules');
    }
};
