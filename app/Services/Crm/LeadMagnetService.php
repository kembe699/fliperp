<?php

namespace App\Services\Crm;

use App\Jobs\SendCrmEmailJob;
use App\Models\Company;
use App\Models\CrmEmail;
use App\Models\CrmLeadMagnet;
use App\Models\CrmLeadMagnetSubmission;
use App\Support\Url;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lead magnets: a file or link offered on a public page. Someone who fills in
 * the form becomes (or is matched to) a CRM contact and is emailed a personal
 * download link, which also counts downloads.
 */
class LeadMagnetService
{
    public const DISK = 'local';

    public function __construct(protected MarketingContactService $contacts) {}

    public function storeFile(CrmLeadMagnet $magnet, UploadedFile $file): void
    {
        if ($magnet->file_path) {
            Storage::disk(self::DISK)->delete($magnet->file_path);
        }

        $path = $file->store("crm/lead-magnets/{$magnet->company_id}", self::DISK);
        $magnet->update(['file_path' => $path, 'file_name' => $file->getClientOriginalName()]);
    }

    public function deleteFile(CrmLeadMagnet $magnet): void
    {
        if ($magnet->file_path) {
            Storage::disk(self::DISK)->delete($magnet->file_path);
        }
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, company_name?: ?string}  $data
     */
    public function submit(CrmLeadMagnet $magnet, array $data): CrmLeadMagnetSubmission
    {
        $contact = $this->contacts->resolve($magnet->company_id, $data, "Lead magnet: {$magnet->title}");

        $submission = CrmLeadMagnetSubmission::create([
            'company_id' => $magnet->company_id,
            'lead_magnet_id' => $magnet->id,
            'lead_id' => $contact['lead']?->id,
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'download_token' => Str::random(48),
        ]);

        $companyName = Company::find($magnet->company_id)?->name ?? config('app.name');
        $firstName = Str::before(trim($data['name']), ' ');

        // Logged as a CRM email so it shows on the contact's Emails tab.
        $email = CrmEmail::create([
            'company_id' => $magnet->company_id,
            'lead_id' => $contact['lead']?->id,
            'customer_id' => $contact['customer']?->id,
            'to_email' => $submission->email,
            'to_name' => $submission->name,
            'subject' => "Here's your {$magnet->title}",
            'body' => "Hi {$firstName},\n\nThanks for your interest! Here is your copy of \"{$magnet->title}\":\n\n"
                .$this->downloadUrl($submission)."\n\n"
                ."If you have any questions, just reply to this email.\n\n{$companyName}",
            'sent_by' => $magnet->created_by,
            'status' => 'queued',
        ]);
        SendCrmEmailJob::dispatch($email->id);

        return $submission;
    }

    public function downloadUrl(CrmLeadMagnetSubmission $submission): string
    {
        return Url::api("public/lead-magnets/download/{$submission->download_token}");
    }
}
