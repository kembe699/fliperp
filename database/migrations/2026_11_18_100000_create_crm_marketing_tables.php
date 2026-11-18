<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM marketing: public booking pages (clients book a meeting slot), lead
 * magnets (a download offered for contact details), email campaigns with
 * per-recipient open/click tracking, and the unsubscribe list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_booking_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            // ISO weekdays the page takes bookings on: 1 = Monday … 7 = Sunday.
            $table->json('days');
            $table->string('start_time', 5)->default('09:00');
            $table->string('end_time', 5)->default('17:00');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('min_notice_hours')->default(2);
            $table->unsignedSmallInteger('max_days_ahead')->default(30);
            $table->foreignId('host_user_id')->constrained('users');
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('company_id');
        });

        Schema::table('crm_meetings', function (Blueprint $table) {
            $table->foreignId('booking_page_id')->nullable()->after('customer_id')->constrained('crm_booking_pages')->nullOnDelete();
            $table->text('booking_notes')->nullable()->after('meeting_link');
        });

        Schema::create('crm_lead_magnets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('button_label', 60)->default('Get it now');
            $table->text('thank_you_message')->nullable();
            $table->boolean('ask_phone')->default(false);
            $table->boolean('ask_company')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('company_id');
        });

        Schema::create('crm_lead_magnet_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_magnet_id')->constrained('crm_lead_magnets')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('download_token', 64)->unique();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('crm_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            // { type: leads|customers|all, lead_statuses?: [], lead_sources?: [], lead_magnet_id?: int }
            $table->json('audience');
            // draft → scheduled → sending → sent (or cancelled)
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index('company_id');
        });

        Schema::create('crm_email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('crm_email_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('token', 64)->unique();
            // pending → sent | failed
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'email']);
            $table->index(['campaign_id', 'status']);
        });

        // Automatic emails (lead magnet downloads) have no staff sender.
        Schema::table('crm_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('sent_by')->nullable()->change();
        });

        Schema::create('crm_email_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('campaign_id')->nullable()->constrained('crm_email_campaigns')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_email_unsubscribes');
        Schema::dropIfExists('crm_email_campaign_recipients');
        Schema::dropIfExists('crm_email_campaigns');
        Schema::dropIfExists('crm_lead_magnet_submissions');
        Schema::dropIfExists('crm_lead_magnets');
        Schema::table('crm_meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_page_id');
            $table->dropColumn('booking_notes');
        });
        Schema::dropIfExists('crm_booking_pages');
    }
};
