<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the person who raised a record may approve it themselves (see
 * App\Support\MakerChecker). Off keeps the two-person rule as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('allow_self_approval')->default(false)->after('enabled_modules');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('allow_self_approval');
        });
    }
};
