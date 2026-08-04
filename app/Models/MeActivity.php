<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeActivity extends Model
{
    protected $table = 'me_activities';

    protected $fillable = [
        'me_project_id',
        'name',
        'start_date',
        'end_date',
        'responsible_employee_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeProject::class, 'me_project_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }
}
