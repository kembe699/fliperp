<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CrmService;
use App\Models\Customer;
use App\Models\User;
use App\Services\Crm\AccountAssignmentService;
use App\Services\Crm\ActivityService;
use App\Services\Crm\CustomerServiceRecordService;
use App\Services\Crm\DealService;
use App\Services\Crm\LeadService;
use App\Services\Crm\PipelineService;
use App\Services\Crm\ServiceCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class CrmDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'demo-company')->first();

        if (! $company) {
            return;
        }

        $branch = $company->branches()->where('is_main', true)->first();
        $customer = Customer::where('company_id', $company->id)->where('customer_type', 'credit')->first();
        $admin = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_admin'))
            ->first();
        $branchManager = User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'branch_manager'))
            ->first();

        if (! $branch || ! $customer || ! $admin) {
            return;
        }

        if (CrmService::where('company_id', $company->id)->exists()) {
            return;
        }

        // Every CRM service relies on Auth for company scoping and the
        // acting user, so we act as a real company_admin while seeding.
        Auth::login($admin);

        $serviceCatalogService = app(ServiceCatalogService::class);
        $pipelineService = app(PipelineService::class);
        $leadService = app(LeadService::class);
        $dealService = app(DealService::class);
        $customerServiceRecordService = app(CustomerServiceRecordService::class);
        $accountAssignmentService = app(AccountAssignmentService::class);
        $activityService = app(ActivityService::class);

        // --- Pipeline stages ---
        $stageDefinitions = [
            ['name' => 'New Lead', 'position' => 1, 'color' => '#94a3b8'],
            ['name' => 'Contacted', 'position' => 2, 'color' => '#60a5fa'],
            ['name' => 'Proposal Sent', 'position' => 3, 'color' => '#fbbf24'],
            ['name' => 'Negotiation', 'position' => 4, 'color' => '#f97316'],
            ['name' => 'Closed Won', 'position' => 5, 'color' => '#22c55e', 'is_closed_won' => true],
            ['name' => 'Closed Lost', 'position' => 6, 'color' => '#64748b', 'is_closed_lost' => true],
        ];

        $stages = collect($stageDefinitions)->mapWithKeys(
            fn (array $definition) => [$definition['name'] => $pipelineService->create($definition)]
        );

        // --- Service catalog ---
        $services = collect([
            ['name' => 'Website Design', 'category' => 'Design', 'default_price' => 1500, 'description' => 'A custom-designed marketing website.'],
            ['name' => 'SEO Package', 'category' => 'Marketing', 'default_price' => 400, 'description' => 'Monthly search engine optimization retainer.'],
            ['name' => 'Cloud Hosting', 'category' => 'Infrastructure', 'default_price' => 120, 'description' => 'Managed monthly cloud hosting.'],
            ['name' => 'IT Support Retainer', 'category' => 'Support', 'default_price' => 600, 'description' => 'Monthly on-call IT support hours.'],
        ])->mapWithKeys(fn (array $definition) => [$definition['name'] => $serviceCatalogService->create($definition)]);

        // --- Leads ---
        $openLead = $leadService->create([
            'branch_id' => $branch->id,
            'name' => 'Michael Otieno',
            'company_name' => 'Otieno Traders',
            'email' => 'michael.otieno@example.test',
            'phone' => '+254700111222',
            'source' => 'referral',
            'assigned_to' => $admin->id,
            'notes' => 'Met at the Juba trade fair, interested in a new website.',
        ]);

        $leadToConvert = $leadService->create([
            'branch_id' => $branch->id,
            'name' => 'Grace Nabirye',
            'company_name' => 'Nabirye Retail',
            'email' => 'grace.nabirye@example.test',
            'phone' => '+256700222333',
            'source' => 'website',
            'assigned_to' => $admin->id,
            'notes' => 'Requested a quote for a full website rebuild.',
        ]);

        $disqualifiedLead = $leadService->create([
            'branch_id' => $branch->id,
            'name' => 'Peter Lual',
            'source' => 'cold_call',
            'assigned_to' => $branchManager?->id ?? $admin->id,
            'notes' => 'Budget was far below what we could deliver for.',
        ]);
        $leadService->update($disqualifiedLead, ['status' => 'disqualified']);

        // --- A deal on the still-open lead, sitting in "Contacted" ---
        $dealOnLead = $dealService->create([
            'lead_id' => $openLead->id,
            'pipeline_stage_id' => $stages['New Lead']->id,
            'crm_service_id' => $services['Website Design']->id,
            'title' => 'Otieno Traders — new website',
            'value' => 1500,
            'expected_close_date' => now()->addDays(21)->toDateString(),
            'assigned_to' => $admin->id,
        ]);
        $dealService->moveStage($dealOnLead, $stages['Contacted']->id);

        // --- Convert Grace's lead to a customer, then run her deal to Closed Won ---
        $dealBeforeConversion = $dealService->create([
            'lead_id' => $leadToConvert->id,
            'pipeline_stage_id' => $stages['Proposal Sent']->id,
            'crm_service_id' => $services['Website Design']->id,
            'title' => 'Nabirye Retail — website rebuild',
            'value' => 1500,
            'expected_close_date' => now()->addDays(14)->toDateString(),
            'assigned_to' => $admin->id,
        ]);

        $convertedLead = $leadService->convert($leadToConvert);
        $newCustomer = $convertedLead->convertedCustomer;

        $dealService->moveStage($dealBeforeConversion->fresh(), $stages['Negotiation']->id);
        $dealService->moveStage($dealBeforeConversion->fresh(), $stages['Closed Won']->id);

        // --- A deal on the existing demo customer, lost in negotiation ---
        $lostDeal = $dealService->create([
            'customer_id' => $customer->id,
            'pipeline_stage_id' => $stages['Negotiation']->id,
            'crm_service_id' => $services['SEO Package']->id,
            'title' => 'Jane Kintu — SEO retainer',
            'value' => 400,
            'assigned_to' => $branchManager?->id ?? $admin->id,
        ]);
        $dealService->moveStage($lostDeal, $stages['Closed Lost']->id, 'Chose a competitor with a lower monthly rate.');

        // --- Customer services actually sold ---
        $customerServiceRecordService->create([
            'customer_id' => $customer->id,
            'crm_service_id' => $services['Cloud Hosting']->id,
            'price_charged' => 120,
            'start_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ]);

        $customerServiceRecordService->create([
            'customer_id' => $newCustomer->id,
            'crm_service_id' => $services['Website Design']->id,
            'deal_id' => $dealBeforeConversion->id,
            'price_charged' => 1500,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // --- Account assignments ---
        $accountAssignmentService->assign([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'role' => 'primary',
        ]);
        if ($branchManager) {
            $accountAssignmentService->assign([
                'customer_id' => $customer->id,
                'user_id' => $branchManager->id,
                'role' => 'support',
            ]);
        }
        $accountAssignmentService->assign([
            'customer_id' => $newCustomer->id,
            'user_id' => $admin->id,
            'role' => 'primary',
        ]);

        // --- Activities, including one still-open complaint ---
        $activityService->create([
            'customer_id' => $customer->id,
            'type' => 'call',
            'subject' => 'Quarterly check-in call',
            'description' => 'Walked through hosting usage and upcoming renewal.',
            'activity_date' => now()->subDays(5)->toDateString(),
        ]);

        $activityService->create([
            'customer_id' => $newCustomer->id,
            'deal_id' => $dealBeforeConversion->id,
            'type' => 'note',
            'subject' => 'Kickoff notes',
            'description' => 'Sent the design brief template for the new site.',
            'activity_date' => now()->toDateString(),
        ]);

        $activityService->create([
            'customer_id' => $customer->id,
            'type' => 'complaint',
            'subject' => 'Site was slow for two days',
            'description' => 'Customer reported degraded hosting performance after a traffic spike.',
            'activity_date' => now()->subDays(1)->toDateString(),
        ]);

        Auth::logout();
    }
}
