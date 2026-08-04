<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::recordAudit('created', $model, null, $model->getAttributes()));

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                static::recordAudit('updated', $model, $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(fn (Model $model) => static::recordAudit('deleted', $model, $model->getOriginal(), null));
    }

    protected static function recordAudit(string $action, Model $model, ?array $old, ?array $new): void
    {
        $exclude = array_flip(array_merge(['password', 'remember_token'], $model->auditExclude ?? []));

        app(AuditLogService::class)->record(
            $action,
            $model,
            $old !== null ? array_diff_key($old, $exclude) : $old,
            $new !== null ? array_diff_key($new, $exclude) : $new,
        );
    }
}
