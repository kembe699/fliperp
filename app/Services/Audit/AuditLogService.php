<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function record(string $action, Model $model, ?array $oldValues, ?array $newValues): AuditLog
    {
        return AuditLog::create([
            'company_id' => $this->resolveCompanyId($model),
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => class_basename($model),
            'record_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    protected function resolveCompanyId(Model $model): ?int
    {
        if ($model instanceof Company) {
            return $model->getKey();
        }

        return $model->getAttribute('company_id');
    }
}
