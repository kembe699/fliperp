<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends TenantModel
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'photo_url',
        'national_id',
        'phone',
        'email',
        'hire_date',
        'termination_date',
        'employment_type',
        'status',
        'bank_name',
        'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'national_id' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function currentSalaryStructure(): ?SalaryStructure
    {
        return $this->salaryStructures()->orderByDesc('effective_date')->first();
    }
}
