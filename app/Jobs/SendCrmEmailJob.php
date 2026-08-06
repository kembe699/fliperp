<?php

namespace App\Jobs;

use App\Mail\CrmDirectEmail;
use App\Mail\CrmMeetingConfirmation;
use App\Models\Company;
use App\Models\CrmEmail;
use App\Models\CrmMeeting;
use App\Models\Quotation;
use App\Services\Crm\MeetingService;
use App\Services\Sales\QuotationService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Deliberately does NOT implement ShouldQueue. This deployment's backend
 * service (see railway.json) only runs `php artisan serve` — no
 * `queue:work` process exists — so with QUEUE_CONNECTION=database a queued
 * job just sits in the jobs table forever. Same root cause and same fix as
 * the chat broadcast delay: dispatch() with no ShouldQueue interface runs
 * handle() synchronously, in-process, right here. Still updates the
 * CrmEmail row's status (sent/failed) itself since a plain Mailable send
 * gives no hook for that.
 */
class SendCrmEmailJob
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public int $crmEmailId) {}

    public function handle(QuotationService $quotationService, MeetingService $meetingService): void
    {
        $email = CrmEmail::query()->find($this->crmEmailId);

        if (! $email) {
            return;
        }

        try {
            $companyName = Company::find($email->company_id)?->name ?? config('app.name');

            if ($email->meeting_id) {
                $meeting = CrmMeeting::query()->with(['organizer', 'attendees.user'])->findOrFail($email->meeting_id);

                Mail::to($email->to_email, $email->to_name)->send(new CrmMeetingConfirmation(
                    meeting: $meeting,
                    companyName: $companyName,
                    recipientName: $email->to_name ?? 'there',
                    icsContent: $meetingService->generateIcs($meeting),
                ));
            } else {
                $pdfContent = null;
                $pdfFilename = null;

                if ($email->quotation_id) {
                    $quotation = Quotation::query()->with(['items.product', 'items.variant', 'customer', 'branch'])->findOrFail($email->quotation_id);
                    $pdf = $quotationService->buildPdf($quotation);
                    $pdfContent = $pdf->output();
                    $pdfFilename = "quotation-{$quotation->reference_number}.pdf";
                }

                Mail::to($email->to_email, $email->to_name)->send(new CrmDirectEmail(
                    subjectLine: $email->subject,
                    bodyText: $email->body,
                    companyName: $companyName,
                    pdfContent: $pdfContent,
                    pdfFilename: $pdfFilename,
                ));
            }

            $email->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $email->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
