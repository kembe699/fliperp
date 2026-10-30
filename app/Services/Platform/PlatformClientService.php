<?php

namespace App\Services\Platform;

use App\Mail\ClientWelcomeEmail;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
                'client_code' => $data['client_code'] ?? null,
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

    /**
     * Permanently removes a client: every row carrying its company_id, its users
     * (with their tokens and roles), the company itself and its billing customer
     * under the platform company. Irreversible, and the client code becomes free.
     *
     * Refuses while the platform company has invoices, quotations or payments
     * billed to the client — those are the platform's own accounting records and
     * must be dealt with first, not silently removed with the client.
     */
    public function deleteClient(Company $company): void
    {
        $billed = $company->billing_customer_id ? [
            'invoices' => DB::table('invoices')->where('customer_id', $company->billing_customer_id)->count(),
            'quotations' => DB::table('quotations')->where('customer_id', $company->billing_customer_id)->count(),
            'payments' => DB::table('customer_payments')->where('customer_id', $company->billing_customer_id)->count(),
        ] : [];
        $billed = array_filter($billed);
        if ($billed) {
            $list = collect($billed)->map(fn (int $count, string $kind) => "{$count} {$kind}")->join(', ', ' and ');
            throw ValidationException::withMessages([
                'client' => "{$company->name} still has {$list} in your platform billing. Delete or void them first, then delete the client.",
            ]);
        }

        DB::transaction(function () use ($company) {
            $userIds = DB::table('users')->where('company_id', $company->id)->pluck('id');

            $this->deleteCompanyRows($company->id);
            DB::table('companies')->where('id', $company->id)->delete();

            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
            DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();

            if ($company->billing_customer_id) {
                DB::table('customers')->where('id', $company->billing_customer_id)->delete();
            }

            // Recorded under the platform company: the client's own audit trail is gone with it.
            AuditLog::create([
                'company_id' => Auth::user()->company_id,
                'user_id' => Auth::id(),
                'action' => 'deleted',
                'module' => 'Company',
                'record_id' => $company->id,
                'old_values' => [
                    'name' => $company->name,
                    'client_code' => $company->client_code,
                    'status' => $company->status,
                    'user_count' => $userIds->count(),
                ],
                'new_values' => null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Deletes every row in every table that carries this company_id. Some of
     * those rows reference each other through RESTRICT keys (deal stage history
     * points at pipeline stages, sales at users…), so no single table order
     * works for every client: each table is tried under its own savepoint, and
     * blocked ones are retried on the next pass once what references them is gone.
     */
    protected function deleteCompanyRows(int $companyId): void
    {
        $tables = collect(DB::select(
            "SELECT c.table_name FROM information_schema.columns c
             JOIN information_schema.tables t ON t.table_name = c.table_name AND t.table_schema = c.table_schema
             WHERE c.column_name = 'company_id' AND c.table_schema = current_schema() AND t.table_type = 'BASE TABLE'
               AND c.table_name NOT IN ('companies', 'users', 'audit_logs')"
        ))->pluck('table_name');

        for ($pass = 1; $tables->isNotEmpty(); $pass++) {
            $tables = $tables->reject(function (string $table) use ($companyId) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('company_id', $companyId)->delete());

                    return true;
                } catch (QueryException $e) {
                    if ($e->getCode() !== '23503') {
                        throw $e;
                    }

                    return false;
                }
            })->values();

            if ($pass >= 25 && $tables->isNotEmpty()) {
                throw new \RuntimeException('Could not delete client data; still blocked: '.$tables->join(', '));
            }
        }
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
