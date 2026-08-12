<?php

namespace App\Services\Platform;

use App\Mail\ClientWelcomeEmail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PlatformClientService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
            ->where('is_platform', false)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('client_code', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * One transaction: tenant company (status=pending), its main branch, a billing
     * customer record inside the PLATFORM company's own books (billed via the
     * existing Invoice/Quotation modules — no parallel billing system), and the
     * client's first admin user with a generated temp password. The welcome email
     * is best-effort: a delivery failure must never undo an already-onboarded
     * client, so it's caught rather than left to roll back the transaction.
     */
    public function createClient(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $platformCompany = Company::where('is_platform', true)->firstOrFail();

            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(6)),
                'currency_code' => $data['currency_code'] ?? 'USD',
                'timezone' => $data['timezone'] ?? 'UTC',
                'is_active' => true,
                'status' => 'pending',
                'onboarded_by' => Auth::id(),
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => $data['branch_name'] ?? 'Main Branch',
                'code' => 'MAIN',
                'address' => $data['branch_address'] ?? null,
                'phone' => $data['branch_phone'] ?? null,
                'is_main' => true,
                'is_active' => true,
            ]);

            $billingCustomer = Customer::create([
                'company_id' => $platformCompany->id,
                'name' => $data['company_name'],
                'phone' => $data['billing_phone'] ?? '+000000000',
                'email' => $data['admin_email'],
                'customer_type' => 'credit',
                'is_active' => true,
            ]);

            $company->update(['billing_customer_id' => $billingCustomer->id]);

            $tempPassword = Str::password(12);

            $admin = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($tempPassword),
                'is_active' => true,
            ]);
            $admin->assignRole('company_admin');

            try {
                Mail::to($admin->email, $admin->name)->send(new ClientWelcomeEmail(
                    companyName: $company->name,
                    clientCode: $company->client_code,
                    adminEmail: $admin->email,
                    tempPassword: $tempPassword,
                    loginUrl: rtrim(config('app.frontend_url'), '/').'/login',
                ));
            } catch (\Throwable $e) {
                Log::error('Client welcome email failed to send', ['company_id' => $company->id, 'error' => $e->getMessage()]);
            }

            return [
                'company' => $company->fresh(),
                'admin_user' => $admin,
                'temp_password' => $tempPassword,
            ];
        });
    }

    public function suspend(Company $company): Company
    {
        $company->update(['status' => 'suspended', 'suspended_at' => now()]);

        return $company->fresh();
    }

    public function activate(Company $company): Company
    {
        $company->update(['status' => 'active', 'activated_at' => now()]);

        return $company->fresh();
    }
}
