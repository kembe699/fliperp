<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when this email is the automatic "meeting confirmation" sent to
     * the external contact when a meeting is scheduled — lets the queued
     * job know to use the meeting-confirmation template instead of the
     * free-form CrmDirectEmail one.
     */
    public function up(): void
    {
        Schema::table('crm_emails', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('quotation_id')->constrained('crm_meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
        });
    }
};
