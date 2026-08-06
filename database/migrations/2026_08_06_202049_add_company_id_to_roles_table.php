<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * NULL means a system role (one of the fixed set seeded by
     * RoleAndPermissionSeeder — company_admin, cashier, etc.), shared and
     * assignable across every company. A non-null value scopes a
     * company-created custom role to that company only: other companies
     * can't see, assign, edit, or delete it, and only a platform-level
     * super_admin (company_id === null on the user) can touch a system role.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('guard_name')->constrained('companies')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
