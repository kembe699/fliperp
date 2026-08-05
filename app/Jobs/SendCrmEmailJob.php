<?php

namespace App\Jobs;

use App\Mail\CrmDirectEmail;
use App\Models\Company;
use App\Models\CrmEmail;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Actually delivers a queued CrmEmail row and reflects the outcome back onto
 * it (sent/failed) — a queued Mailable alone gives no hook to update our own
 * status column, so the send happens here instead.
 */
class SendCrmEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $crmEmailId) {}

    public function handle(QuotationService $quotationService): void
    {
        $email = CrmEmail::query()->find($this->crmEmailId);

        if (! $email) {
            return;
        }

        try {
            $pdfContent = null;
            $pdfFilename = null;

            if ($email->quotation_id) {
                $quotation = Quotation::query()->with(['items.product', 'items.variant', 'customer', 'branch'])->findOrFail($email->quotation_id);
                $pdf = $quotationService->buildPdf($quotation);
                $pdfContent = $pdf->output();
                $pdfFilename = "quotation-{$quotation->reference_number}.pdf";
            }

            $companyName = Company::find($email->company_id)?->name ?? config('app.name');

            Mail::to($email->to_email, $email->to_name)->send(new CrmDirectEmail(
                subjectLine: $email->subject,
                bodyText: $email->body,
                companyName: $companyName,
                pdfContent: $pdfContent,
                pdfFilename: $pdfFilename,
            ));

            $email->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $email->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
