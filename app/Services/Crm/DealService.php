<?php

namespace App\Services\Crm;

use App\Http\Resources\CrmActivityResource;
use App\Http\Resources\CrmDealResource;
use App\Http\Resources\CrmEmailResource;
use App\Http\Resources\CrmMeetingResource;
use App\Http\Resources\CrmServiceResource;
use App\Models\CrmDeal;
use App\Models\CrmDealStageHistory;
use App\Models\CrmPipelineStage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmDeal::query()
            ->with(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo'])
            ->when($filters['pipeline_stage_id'] ?? null, fn ($query, $id) => $query->where('pipeline_stage_id', $id))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->when($filters['lead_id'] ?? null, fn ($query, $id) => $query->where('lead_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * All non-deleted deals grouped by pipeline stage (ordered by stage
     * position), shaped for direct Kanban board consumption.
     *
     * @return Collection<int, array{stage: CrmPipelineStage, deals: Collection<int, CrmDeal>}>
     */
    public function kanban(): Collection
    {
        $stages = CrmPipelineStage::query()->orderBy('position')->get();

        $deals = CrmDeal::query()
            ->with(['lead', 'customer', 'service', 'assignedTo'])
            ->whereIn('pipeline_stage_id', $stages->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('pipeline_stage_id');

        return $stages->map(fn (CrmPipelineStage $stage) => [
            'stage' => $stage,
            'deals' => $deals->get($stage->id, collect()),
        ]);
    }

    public function create(array $data): CrmDeal
    {
        return DB::transaction(function () use ($data) {
            $deal = CrmDeal::create($data);

            CrmDealStageHistory::create([
                'deal_id' => $deal->id,
                'from_stage_id' => null,
                'to_stage_id' => $deal->pipeline_stage_id,
                'moved_by' => Auth::id(),
                'moved_at' => now(),
            ]);

            return $deal->load(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo']);
        });
    }

    public function update(CrmDeal $deal, array $data): CrmDeal
    {
        if ($deal->closed_at) {
            throw ValidationException::withMessages([
                'deal' => ['A closed deal cannot be edited.'],
            ]);
        }

        $deal->update($data);

        return $deal->fresh(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo']);
    }

    public function delete(CrmDeal $deal): void
    {
        $deal->delete();
    }

    /**
     * @return array{deal: CrmDeal, prompt_customer_service_log: bool}
     */
    public function moveStage(CrmDeal $deal, int $toStageId, ?string $lostReason = null): array
    {
        $toStage = CrmPipelineStage::query()
            ->where('company_id', $deal->company_id)
            ->find($toStageId);

        if (! $toStage) {
            throw ValidationException::withMessages([
                'pipeline_stage_id' => ['The selected pipeline stage is invalid.'],
            ]);
        }

        if ($toStage->is_closed_lost && ! $lostReason) {
            throw ValidationException::withMessages([
                'lost_reason' => ['A reason is required when moving a deal to a closed-lost stage.'],
            ]);
        }

        return DB::transaction(function () use ($deal, $toStage, $lostReason) {
            $fromStageId = $deal->pipeline_stage_id;

            $updates = ['pipeline_stage_id' => $toStage->id];

            if ($toStage->is_closed_won || $toStage->is_closed_lost) {
                $updates['closed_at'] = now();
            }

            if ($toStage->is_closed_lost) {
                $updates['lost_reason'] = $lostReason;
            }

            $deal->update($updates);

            CrmDealStageHistory::create([
                'deal_id' => $deal->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStage->id,
                'moved_by' => Auth::id(),
                'moved_at' => now(),
            ]);

            return [
                'deal' => $deal->fresh(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo']),
                'prompt_customer_service_log' => $toStage->is_closed_won,
            ];
        });
    }

    public function syncServices(CrmDeal $deal, array $serviceIds): CrmDeal
    {
        $deal->services()->sync($serviceIds);

        return $deal->fresh('services');
    }

    public function detail(CrmDeal $deal): array
    {
        $deal->load(['lead', 'customer', 'pipelineStage', 'service', 'assignedTo', 'services']);

        return [
            'deal' => new CrmDealResource($deal),
            'services' => CrmServiceResource::collection($deal->services),
            'meetings' => [
                'upcoming' => CrmMeetingResource::collection(
                    $deal->meetings()->with(['organizer', 'attendees.user'])->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->get()
                ),
                'past' => CrmMeetingResource::collection(
                    $deal->meetings()->with(['organizer', 'attendees.user'])->where('scheduled_at', '<', now())->orderByDesc('scheduled_at')->get()
                ),
            ],
            'emails' => CrmEmailResource::collection($deal->emails()->with('sentBy')->latest()->limit(20)->get()),
            'activities' => CrmActivityResource::collection($deal->activities()->with(['loggedBy', 'resolvedBy'])->latest('activity_date')->get()),
            'quotations' => $deal->quotations()->orderByDesc('quotations.created_at')->get()->map(fn ($quotation) => [
                'id' => $quotation->id,
                'reference_number' => $quotation->reference_number,
                'status' => $quotation->status,
                'total_amount' => (float) $quotation->total_amount,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'created_at' => $quotation->created_at?->toIso8601String(),
            ]),
        ];
    }
}
