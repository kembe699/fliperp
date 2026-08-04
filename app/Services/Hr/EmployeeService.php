<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(protected ImageUploadService $imageUploadService) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Employee::query()->latest()->paginate($perPage);
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    public function uploadPhoto(Employee $employee, UploadedFile $file): Employee
    {
        $url = $this->imageUploadService->replace($file, 'employees', $employee->photo_url);
        $employee->update(['photo_url' => $url]);

        return $employee;
    }

    public function deletePhoto(Employee $employee): Employee
    {
        $this->imageUploadService->delete($employee->photo_url);
        $employee->update(['photo_url' => null]);

        return $employee;
    }

    /**
     * Creates a restricted "employee" portal login for staff self-service
     * (clock in/out, leave requests) and links it back to the employee
     * record. Returns the plaintext temporary password once — it is never
     * stored or retrievable again, so HR must relay it to the employee now.
     */
    public function createPortalAccount(Employee $employee): array
    {
        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'user_id' => ['This employee already has a portal login.'],
            ]);
        }

        if (! $employee->email) {
            throw ValidationException::withMessages([
                'email' => ['The employee needs an email address before a portal login can be created.'],
            ]);
        }

        if (User::where('email', $employee->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user account with this email already exists.'],
            ]);
        }

        $temporaryPassword = Str::password(12);

        $user = User::create([
            'company_id' => $employee->company_id,
            'branch_id' => $employee->branch_id,
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'email' => $employee->email,
            'password' => Hash::make($temporaryPassword),
            'is_active' => true,
        ]);

        $user->assignRole('employee');
        $employee->update(['user_id' => $user->id]);

        return ['employee' => $employee->fresh(), 'temporary_password' => $temporaryPassword];
    }
}
