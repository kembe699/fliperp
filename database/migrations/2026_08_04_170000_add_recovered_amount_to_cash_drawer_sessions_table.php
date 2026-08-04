<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_sessions', function (Blueprint $table) {
            $table->decimal('recovered_amount', 15, 2)->default(0)->after('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawer_sessions', function (Blueprint $table) {
            $table->dropColumn('recovered_amount');
        });
    }
};
