<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('client_code')->nullable()->unique()->after('slug');
            // Every OTHER company-creation path in the app (self-registration, demo/test
            // seeding, the Pest factory) explicitly sets status='active' to preserve existing
            // behavior — this default only takes effect for the new platform-onboarding flow,
            // which deliberately doesn't pass a status so a fresh client starts pending.
            $table->enum('status', ['active', 'suspended', 'pending'])->default('pending')->after('is_active');
            $table->boolean('is_platform')->default(false)->after('status');
            $table->foreignId('billing_customer_id')->nullable()->after('is_platform')
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('onboarded_by')->nullable()->after('billing_customer_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('onboarded_by');
            $table->timestamp('activated_at')->nullable()->after('suspended_at');
        });

        // Backfill: every company that already existed before this migration needs a
        // client_code too (it's how login resolves a tenant now) and must not be left
        // 'pending' (the new enum's default) — an already-operating company should stay
        // usable, only brand-new platform-onboarded clients start pending.
        DB::table('companies')->whereNull('client_code')->orderBy('id')->pluck('id')->each(function ($id) {
            do {
                $code = 'NHC-'.strtoupper(Str::random(5));
            } while (DB::table('companies')->where('client_code', $code)->exists());

            DB::table('companies')->where('id', $id)->update(['client_code' => $code, 'status' => 'active']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_customer_id');
            $table->dropConstrainedForeignId('onboarded_by');
            $table->dropColumn(['client_code', 'status', 'is_platform', 'suspended_at', 'activated_at']);
        });
    }
};
