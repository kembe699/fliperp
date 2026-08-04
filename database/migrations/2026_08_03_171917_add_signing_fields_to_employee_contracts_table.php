<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->text('contract_body')->nullable()->after('currency_code');
            $table->enum('status', ['draft', 'generated', 'signed'])->default('draft')->after('contract_body');
            $table->timestamp('signed_at')->nullable()->after('status');
            $table->string('signed_by_name')->nullable()->after('signed_at');
            $table->longText('signature_data')->nullable()->after('signed_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['contract_body', 'status', 'signed_at', 'signed_by_name', 'signature_data']);
        });
    }
};
