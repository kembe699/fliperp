<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with('lines.account')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('entry_date')
            ->paginate($perPage);
    }

    public function create(array $data): JournalEntry
    {
        $this->assertBalanced($data['lines']);

        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'branch_id' => $data['branch_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? null,
                'source_module' => $data['source_module'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $entry->lines()->createMany($this->normalizeLines($data['lines']));

            return $entry->load('lines.account');
        });
    }

    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be edited.'],
            ]);
        }

        if (isset($data['lines'])) {
            $this->assertBalanced($data['lines']);
        }

        return DB::transaction(function () use ($entry, $data) {
            $entry->update(array_filter([
                'branch_id' => $data['branch_id'] ?? $entry->branch_id,
                'reference_number' => $data['reference_number'] ?? $entry->reference_number,
                'entry_date' => $data['entry_date'] ?? $entry->entry_date,
                'description' => $data['description'] ?? $entry->description,
                'source_module' => $data['source_module'] ?? $entry->source_module,
                'source_id' => $data['source_id'] ?? $entry->source_id,
            ], fn ($value) => $value !== null));

            if (isset($data['lines'])) {
                $entry->lines()->delete();
                $entry->lines()->createMany($this->normalizeLines($data['lines']));
            }

            return $entry->fresh('lines.account');
        });
    }

    public function delete(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be deleted.'],
            ]);
        }

        $entry->delete();
    }

    /**
     * Used internally by other modules (payroll, depreciation, ...) to create
     * an already-posted, balanced journal entry in a single step.
     */
    public function postModuleEntry(array $data): JournalEntry
    {
        $entry = $this->create($data);

        return $this->post($entry);
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft journal entries can be posted.'],
            ]);
        }

        $this->assertPeriodOpen($entry->company_id, $entry->entry_date->toDateString());

        $entry->update([
            'status' => 'posted',
            'posted_by' => Auth::id(),
        ]);

        return $entry->fresh('lines.account');
    }

    public function reverse(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'status' => ['Only posted journal entries can be reversed.'],
            ]);
        }

        $this->assertPeriodOpen($entry->company_id, $entry->entry_date->toDateString());

        return DB::transaction(function () use ($entry) {
            $reversal = JournalEntry::create([
                'branch_id' => $entry->branch_id,
                'reference_number' => $entry->reference_number.'-REV',
                'entry_date' => now()->toDateString(),
                'description' => "Reversal of {$entry->reference_number}",
                'source_module' => $entry->source_module,
                'source_id' => $entry->source_id,
                'status' => 'posted',
                'posted_by' => Auth::id(),
            ]);

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description,
                ]);
            }

            $entry->update(['status' => 'reversed']);

            return $reversal->load('lines.account');
        });
    }

    protected function assertPeriodOpen(int $companyId, string $entryDate): void
    {
        $isClosed = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'entry_date' => ['This entry date falls within a closed accounting period and cannot be posted or reversed.'],
            ]);
        }
    }

    protected function normalizeLines(array $lines): array
    {
        return array_map(fn (array $line) => [
            'account_id' => $line['account_id'],
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0,
            'description' => $line['description'] ?? null,
        ], $lines);
    }

    protected function assertBalanced(array $lines): void
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += (int) round(($line['debit'] ?? 0) * 100);
            $totalCredit += (int) round(($line['credit'] ?? 0) * 100);
        }

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => ['Journal entry debit and credit totals must balance.'],
            ]);
        }
    }
}
