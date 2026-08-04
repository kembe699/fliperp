<?php

namespace App\Services\Reporting;

use App\Models\ReportSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ReportSnapshotService
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return ReportSnapshot::query()
            ->when($filters['report_type'] ?? null, fn ($query, $type) => $query->where('report_type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('period_start', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('period_end', '<=', $to))
            ->latest('generated_at')
            ->paginate($perPage);
    }

    public function save(string $reportType, string $periodStart, string $periodEnd, array $data): ReportSnapshot
    {
        return ReportSnapshot::create([
            'report_type' => $reportType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => Auth::id(),
            'data' => $data,
            'generated_at' => now(),
        ]);
    }
}
