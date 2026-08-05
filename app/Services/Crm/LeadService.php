<?php

namespace App\Services\Crm;

use App\Http\Resources\CrmActivityResource;
use App\Http\Resources\CrmEmailResource;
use App\Http\Resources\CrmLeadResource;
use App\Http\Resources\CrmMeetingResource;
use App\Http\Resources\CrmServiceResource;
use App\Models\Customer;
use App\Models\CrmLead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return CrmLead::query()
            ->with(['assignedTo', 'branch'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
            ))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): CrmLead
    {
        return CrmLead::create($data + ['status' => 'open']);
    }

    public function update(CrmLead $lead, array $data): CrmLead
    {
        if ($lead->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only open leads can be edited.'],
            ]);
        }

        $lead->update($data);

        return $lead->fresh(['assignedTo', 'branch']);
    }

    public function delete(CrmLead $lead): void
    {
        $lead->delete();
    }

    public function convert(CrmLead $lead, array $data = []): CrmLead
    {
        if ($lead->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only open leads can be converted.'],
            ]);
        }

        $phone = $data['phone'] ?? $lead->phone;

        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => ['This lead has no phone number on file — supply one to convert it to a customer.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $data, $phone) {
            $customer = Customer::create([
                'company_id' => $lead->company_id,
                'branch_id' => $data['branch_id'] ?? $lead->branch_id,
                'name' => $lead->company_name ?: $lead->name,
                'email' => $lead->email,
                'phone' => $phone,
                'customer_type' => $data['customer_type'] ?? 'regular',
                'credit_limit' => $data['credit_limit'] ?? 0,
                'is_active' => true,
            ]);

            $lead->update([
                'status' => 'converted',
                'converted_customer_id' => $customer->id,
            ]);

            $lead->deals()
                ->whereNull('closed_at')
                ->update(['customer_id' => $customer->id]);

            return $lead->fresh(['convertedCustomer', 'deals']);
        });
    }

    public function syncServices(CrmLead $lead, array $serviceIds): CrmLead
    {
        $lead->services()->sync($serviceIds);

        return $lead->fresh('services');
    }

    public function detail(CrmLead $lead): array
    {
        $lead->load(['assignedTo', 'branch', 'services']);

        return [
            'lead' => new CrmLeadResource($lead),
            'services' => CrmServiceResource::collection($lead->services),
            'meetings' => [
                'upcoming' => CrmMeetingResource::collection(
                    $lead->meetings()->with(['organizer', 'attendees.user'])->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->get()
                ),
                'past' => CrmMeetingResource::collection(
                    $lead->meetings()->with(['organizer', 'attendees.user'])->where('scheduled_at', '<', now())->orderByDesc('scheduled_at')->get()
                ),
            ],
            'emails' => CrmEmailResource::collection($lead->emails()->with('sentBy')->latest()->limit(20)->get()),
            'activities' => CrmActivityResource::collection($lead->activities()->with(['loggedBy', 'resolvedBy'])->latest('activity_date')->get()),
            'quotations' => $lead->quotations()->orderByDesc('quotations.created_at')->get()->map(fn ($quotation) => [
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
