<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\CrmLead;
use Illuminate\Support\Str;

/**
 * Turns someone who filled in a public form (a booking page, a lead magnet)
 * into a CRM contact for one company: an existing customer or open lead with
 * the same email is reused, otherwise a new lead is created.
 *
 * Public requests have no signed-in user, so the CompanyScope applies no
 * filter: every query here names the company explicitly.
 */
class MarketingContactService
{
    /**
     * @return array{lead: ?CrmLead, customer: ?Customer}
     */
    public function resolve(int $companyId, array $contact, string $source): array
    {
        $email = Str::lower(trim($contact['email']));

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($customer) {
            return ['lead' => null, 'customer' => $customer];
        }

        $lead = CrmLead::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->latest('id')
            ->first();

        if ($lead) {
            // Fill gaps only — never overwrite what staff already recorded.
            $lead->fill(array_filter([
                'phone' => $lead->phone ? null : ($contact['phone'] ?? null),
                'company_name' => $lead->company_name ? null : ($contact['company_name'] ?? null),
            ]))->save();

            return ['lead' => $lead, 'customer' => null];
        }

        $lead = CrmLead::create([
            'company_id' => $companyId,
            'name' => trim($contact['name']),
            'email' => $email,
            'phone' => $contact['phone'] ?? null,
            'company_name' => $contact['company_name'] ?? null,
            'source' => Str::limit($source, 250, ''),
            'status' => 'open',
        ]);

        return ['lead' => $lead, 'customer' => null];
    }
}
