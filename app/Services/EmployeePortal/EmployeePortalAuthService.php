<?php

namespace App\Services\EmployeePortal;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeePortalAuthService
{
    public function login(string $email, string $password): array
    {
        // Login runs unauthenticated, so User's CompanyScope is skipped
        // automatically (it only applies once Auth::check() is true) —
        // this search is intentionally unscoped, mirroring AuthService::login().
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        if (! $user->hasRole('employee')) {
            throw ValidationException::withMessages([
                'email' => ['This account does not have employee portal access.'],
            ]);
        }

        $employee = $user->employee;

        if (! $employee) {
            throw ValidationException::withMessages([
                'email' => ['No employee record is linked to this account.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('employee-portal')->plainTextToken;

        return ['token' => $token, 'user' => $user, 'employee' => $employee->load(['branch', 'department', 'position'])];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
