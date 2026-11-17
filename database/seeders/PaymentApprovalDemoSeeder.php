<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PaymentApprovalRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * DEMO TENANT ONLY. Sets up the G711-style payment approval example on the
 * "demo-company" tenant so the workflow can be tried end to end:
 *   - roles: Program Officer, Executive Director, Finance Officer, Data entry, View only
 *   - one demo user per role (password "Password123!")
 *   - rules: up to USD 500 → Program Officer;
 *            above USD 500 → Program Officer, then Executive Director
 *
 * Run by hand: php artisan db:seed --class=PaymentApprovalDemoSeeder
 * It refuses to touch any company other than the demo one, and is not part of
 * DatabaseSeeder or the deploy entrypoint.
 */
class PaymentApprovalDemoSeeder extends Seeder
{
    public const DEMO_SLUG = 'demo-company';

    public function run(): void
    {
        $company = Company::query()->where('slug', self::DEMO_SLUG)->first();
        if (! $company) {
            throw new RuntimeException('The demo company (slug "'.self::DEMO_SLUG.'") was not found. This seeder only runs on the demo tenant.');
        }
        $branch = Branch::query()->where('company_id', $company->id)->where('is_main', true)->first();

        $roles = [
            'Program Officer' => ['payment-requests.view', 'payment-requests.approve', 'reports.view', 'dashboard.view'],
            'Executive Director' => ['payment-requests.view', 'payment-requests.approve', 'payment-requests.cancel', 'payment-approval-rules.view', 'payment-approval-rules.manage', 'reports.view', 'dashboard.view'],
            'Finance Officer' => ['payment-requests.view', 'payment-requests.create', 'payment-requests.update', 'payment-requests.submit', 'payment-requests.cancel', 'payment-requests.pay', 'payment-types.view', 'chart-of-accounts.view', 'suppliers.view', 'supplier-bills.view', 'reports.view', 'dashboard.view'],
            'Data entry' => ['payment-requests.view', 'payment-requests.create', 'payment-requests.update', 'payment-requests.delete', 'payment-requests.submit', 'payment-requests.cancel', 'chart-of-accounts.view', 'suppliers.view', 'dashboard.view'],
            'View only' => ['payment-requests.view', 'payment-approval-rules.view', 'reports.view', 'dashboard.view'],
        ];

        $made = [];
        foreach ($roles as $name => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id]);
            $role->syncPermissions($permissions);
            $made[$name] = $role;

            $email = 'demo.'.str_replace(' ', '.', strtolower($name)).'@example.test';
            $user = User::query()->firstOrCreate(['email' => $email], [
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'name' => "Demo {$name}",
                'password' => Hash::make('Password123!'),
                'is_active' => true,
            ]);
            if ($user->company_id !== $company->id) {
                throw new RuntimeException("{$email} belongs to another company; not touching it.");
            }
            $user->syncRoles([$role]);
        }

        PaymentApprovalRule::withoutGlobalScopes()->where('company_id', $company->id)->get()->each->delete();

        $small = PaymentApprovalRule::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Up to USD 500', 'min_amount' => 0, 'max_amount' => 500, 'is_active' => true]);
        $small->steps()->create(['sequence' => 1, 'label' => 'Program Officer', 'role_id' => $made['Program Officer']->id]);

        $large = PaymentApprovalRule::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Above USD 500', 'min_amount' => 500, 'max_amount' => null, 'is_active' => true]);
        $large->steps()->create(['sequence' => 1, 'label' => 'Program Officer', 'role_id' => $made['Program Officer']->id]);
        $large->steps()->create(['sequence' => 2, 'label' => 'Executive Director', 'role_id' => $made['Executive Director']->id]);

        $this->command?->info("Payment approval demo set up on {$company->name} (id {$company->id}).");
    }
}
