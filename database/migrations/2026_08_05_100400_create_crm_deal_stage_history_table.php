<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deal_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('crm_pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->foreignId('moved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deal_stage_history');
    }
};
