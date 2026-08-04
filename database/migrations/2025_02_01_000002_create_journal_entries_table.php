<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->string('source_module')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->timestamps();

            $table->unique(['company_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
