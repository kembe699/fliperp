<?php

namespace App\Services\Crm;

use App\Models\CrmActivity;
use App\Models\CrmAccountAssignment;
use App\Models\CrmCustomerService;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shape of summary():
 * [
 *   'from' => ?string, 'to' => ?string, 'branch_id' => ?int,
 *   'leads' => ['total' => int, 'by_status' => ['open'=>int,'converted'=>int,'disqualified'=>int]],
 *   'deals' => ['by_stage' => [['stage_id','stage_name','is_closed_won','is_closed_lost','count','total_value'], ...]],
 *   'conversion_rate' => ?float,
 *   'customers_with_active_services' => int,
 *   'total_revenue' => float,
 *   'staff_performance' => [['user_id','user_name','deals_closed_won','total_value'], ...],
 * ]
 */
class CrmReportService
{
    public function summary(int $companyId, ?int $branchId, ?string $from, ?string $to): array
    {
        $leads = CrmLead::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($from && $to, fn ($query) => $query->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]))
            ->get();

        $leadsByStatus = [
            'open' => $leads->where('status', 'open')->count(),
            'converted' => $leads->where('status', 'converted')->count(),
            'disqualified' => $leads->where('status', 'disqualified')->count(),
        ];
        $totalLeads = $leads->count();

        $deals = CrmDeal::query()
            ->with('pipelineStage')
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->where(function ($q) use ($branchId) {
                $q->whereHas('lead', fn ($lead) => $lead->where('branch_id', $branchId))
                    ->orWhereHas('customer', fn ($customer) => $customer->where('branch_id', $branchId));
            }))
            ->when($from && $to, fn ($query) => $query->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]))
            ->get();

        $stages = CrmPipelineStage::query()->where('company_id', $companyId)->orderBy('position')->get();
        $dealsByStage = $stages->map(function (CrmPipelineStage $stage) use ($deals) {
            $stageDeals = $deals->where('pipeline_stage_id', $stage->id);

            return [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'is_closed_won' => $stage->is_closed_won,
                'is_closed_lost' => $stage->is_closed_lost,
                'count' => $stageDeals->count(),
                'total_value' => round((float) $stageDeals->sum('value'), 2),
            ];
        });

        $customersWithActiveServices = CrmCustomerService::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($branchId, fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('branch_id', $branchId)))
            ->distinct('customer_id')
            ->count('customer_id');

        $totalRevenue = CrmCustomerService::query()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('branch_id', $branchId)))
            ->when($from && $to, fn ($query) => $query->whereBetween('start_date', [$from, $to]))
            ->sum('price_charged');

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'leads' => [
                'total' => $totalLeads,
                'by_status' => $leadsByStatus,
            ],
            'deals' => [
                'by_stage' => $dealsByStage->all(),
            ],
            'conversion_rate' => $totalLeads > 0 ? round(($leadsByStatus['converted'] / $totalLeads) * 100, 2) : null,
            'customers_with_active_services' => $customersWithActiveServices,
            'total_revenue' => round((float) $totalRevenue, 2),
            'staff_performance' => $this->staffPerformance($deals),
        ];
    }

    /**
     * @param  Collection<int, CrmDeal>  $deals
     * @return array<int, array{user_id: int, user_name: string, deals_closed_won: int, total_value: float}>
     */
    protected function staffPerformance(Collection $deals): array
    {
        return $deals
            ->filter(fn (CrmDeal $deal) => $deal->assigned_to && $deal->pipelineStage?->is_closed_won)
            ->groupBy('assigned_to')
            ->map(function (Collection $userDeals, int $userId) {
                $user = User::withoutGlobalScopes()->find($userId);

                return [
                    'user_id' => $userId,
                    'user_name' => $user?->name ?? 'Unknown',
                    'deals_closed_won' => $userDeals->count(),
                    'total_value' => round((float) $userDeals->sum('value'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-staff breakdown across every user who has any CRM footprint
     * (an assigned lead/deal, an active account assignment, or a logged
     * activity) in this company.
     *
     * @return array<int, array{
     *   user_id: int, user_name: string, customers_assigned: int,
     *   open_activities_count: int, open_complaints_count: int,
     *   deals: array<int, array{deal_id: int, title: string, stage_name: string, value: float}>,
     *   closed_won_value: float,
     * }>
     */
    public function staff(int $companyId): array
    {
        $userIds = collect()
            ->merge(CrmLead::query()->where('company_id', $companyId)->whereNotNull('assigned_to')->pluck('assigned_to'))
            ->merge(CrmDeal::query()->where('company_id', $companyId)->whereNotNull('assigned_to')->pluck('assigned_to'))
            ->merge(CrmAccountAssignment::query()->where('company_id', $companyId)->active()->pluck('user_id'))
            ->merge(CrmActivity::query()->where('company_id', $companyId)->pluck('logged_by'))
            ->unique()
            ->values();

        $users = User::withoutGlobalScopes()->whereIn('id', $userIds)->get()->keyBy('id');

        return $userIds->map(function (int $userId) use ($companyId, $users) {
            $deals = CrmDeal::query()
                ->with('pipelineStage')
                ->where('company_id', $companyId)
                ->where('assigned_to', $userId)
                ->get();

            $closedWonDeals = $deals->filter(fn (CrmDeal $deal) => $deal->pipelineStage?->is_closed_won);

            return [
                'user_id' => $userId,
                'user_name' => $users->get($userId)?->name ?? 'Unknown',
                'customers_assigned' => CrmAccountAssignment::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $userId)
                    ->active()
                    ->count(),
                'open_activities_count' => CrmActivity::query()
                    ->where('company_id', $companyId)
                    ->where('logged_by', $userId)
                    ->open()
                    ->count(),
                'open_complaints_count' => CrmActivity::query()
                    ->where('company_id', $companyId)
                    ->where('logged_by', $userId)
                    ->complaints()
                    ->open()
                    ->count(),
                'deals' => $deals->map(fn (CrmDeal $deal) => [
                    'deal_id' => $deal->id,
                    'title' => $deal->title,
                    'stage_name' => $deal->pipelineStage?->name,
                    'value' => (float) $deal->value,
                ])->all(),
                'closed_won_value' => round((float) $closedWonDeals->sum('value'), 2),
            ];
        })->values()->all();
    }
}
