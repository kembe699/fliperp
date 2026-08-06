<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AccountingPeriod::query()->latest('start_date')->paginate($perPage);
    }

    public function create(array $data): AccountingPeriod
    {
        return AccountingPeriod::create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'open',
        ]);
    }

    public function update(AccountingPeriod $period, array $data): AccountingPeriod
    {
        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only an open accounting period can be edited.'],
            ]);
        }

        $period->update($data);

        return $period;
    }

    public function delete(AccountingPeriod $period): void
    {
        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only an open accounting period can be deleted.'],
            ]);
        }

        $period->delete();
    }

    public function close(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only an open accounting period can be closed.'],
            ]);
        }

        return DB::transaction(function () use ($period) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);

            // Re-check after the lock: guards against a journal entry being
            // created/posted into this period, or a concurrent close request,
            // landing in the gap between the pre-transaction check above and
            // the checks below.
            if ($period->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => ['Only an open accounting period can be closed.'],
                ]);
            }

            $overlapping = AccountingPeriod::query()
                ->where('company_id', $period->company_id)
                ->where('status', 'closed')
                ->where('id', '!=', $period->id)
                ->where('start_date', '<=', $period->end_date)
                ->where('end_date', '>=', $period->start_date)
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'period' => ['This period overlaps an already-closed accounting period.'],
                ]);
            }

            $draftEntries = JournalEntry::query()
                ->where('company_id', $period->company_id)
                ->where('status', 'draft')
                ->whereBetween('entry_date', [$period->start_date, $period->end_date])
                ->get(['id', 'reference_number', 'entry_date']);

            if ($draftEntries->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'draft_entries' => $draftEntries
                        ->map(fn (JournalEntry $entry) => "Draft entry {$entry->reference_number} dated {$entry->entry_date->toDateString()} must be posted or deleted before this period can be closed.")
                        ->all(),
                ]);
            }

            $period->update([
                'status' => 'closed',
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);

            return $period;
        });
    }
}
