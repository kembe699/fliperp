<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grants & dimensions — the coding block an NGO like Save the Children puts on
 * every transaction line: Account + Cost Centre + Project + SOF (Source of
 * Funding, i.e. the grant) + DEA (Detailed Expenditure Activity, a budget
 * line within a SOF). The account is the existing account_id; the other four
 * are optional ids on every line that can carry one.
 */
return new class extends Migration
{
    /** Tables whose rows carry the full coding block. */
    protected const CODED_TABLES = ['journal_entry_lines', 'invoice_items', 'purchase_order_items', 'supplier_bills'];

    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            // The SOF code, e.g. "SOF-12345".
            $table->string('code', 50);
            $table->string('name');
            // The currency the donor funds and is reported to in (usually USD).
            $table->char('currency_code', 3);
            $table->date('start_date');
            $table->date('end_date');
            // Total award, in currency_code.
            $table->decimal('budget_amount', 18, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('grant_deas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['grant_id', 'code']);
        });

        // The donor budget, in the grant's currency: per account, optionally
        // per DEA within it.
        Schema::create('grant_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('dea_id')->nullable()->constrained('grant_deas')->restrictOnDelete();
            $table->decimal('budgeted_amount', 18, 2);
            $table->timestamps();

            $table->unique(['grant_id', 'account_id', 'dea_id']);
        });

        Schema::create('cost_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('me_projects', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('company_id');
        });

        foreach (self::CODED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('grant_id')->nullable()->constrained('grants')->restrictOnDelete();
                $table->foreignId('dea_id')->nullable()->constrained('grant_deas')->restrictOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('me_projects')->restrictOnDelete();
                $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
            });
        }

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->index('grant_id');
            $table->index('project_id');
            $table->index('cost_centre_id');
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreignId('grant_id')->nullable()->constrained('grants')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('me_projects')->restrictOnDelete();
            $table->foreignId('cost_centre_id')->nullable()->constrained('cost_centres')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grant_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('cost_centre_id');
        });

        foreach (self::CODED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('grant_id');
                $table->dropConstrainedForeignId('dea_id');
                $table->dropConstrainedForeignId('project_id');
                $table->dropConstrainedForeignId('cost_centre_id');
            });
        }

        Schema::table('me_projects', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::dropIfExists('cost_centres');
        Schema::dropIfExists('grant_budget_lines');
        Schema::dropIfExists('grant_deas');
        Schema::dropIfExists('grants');
        Schema::dropIfExists('donors');
    }
};
