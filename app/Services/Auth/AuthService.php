<?php

namespace App\Services\Auth;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * client_code resolves WHICH company's user table to look in — a plain "email
     * exists somewhere" check would leak whether an email is registered at all
     * across every tenant. Not found / wrong company / wrong password all fail
     * with the exact same generic message (standard practice: don't reveal which
     * part was wrong). Auth::check() is false during login (nobody's authenticated
     * yet), so CompanyScope adds no WHERE clause here regardless — no bypass needed.
     */
    public function login(string $clientCode, string $email, string $password): array
    {
        // Case-insensitive, whitespace-tolerant lookups.
        //
        // PostgreSQL compares strings exactly, unlike MySQL, and client codes are
        // stored uppercase (NHC-WMRYI). Anything else the user types — a lowercase
        // code, or an email a phone keyboard has autocapitalised to Admin@... —
        // matched nothing and came back as "credentials are incorrect", which sent
        // people hunting for a password problem that did not exist. A pasted value
        // with a stray leading or trailing space failed the same way.
        //
        // Normalising here rather than only in the form means existing users are
        // fixed without having to retype anything, and any future caller (mobile
        // app, integration) gets the same tolerance.
        $clientCode = trim($clientCode);
        $email = trim($email);

        $company = Company::whereRaw('LOWER(client_code) = ?', [Str::lower($clientCode)])->first();
        $user = $company
            ? User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->where('company_id', $company->id)->first()
            : null;

        if (! $company || ! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'client_code' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($company->status === 'suspended') {
            throw ValidationException::withMessages([
                'client_code' => ['Your account is currently suspended. Please contact support.'],
            ]);
        }

        if ($company->status === 'pending') {
            throw ValidationException::withMessages([
                'client_code' => ['Your account is not yet active. Please contact support to complete activation.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user->load(['company', 'branch', 'roles']),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Self-service profile edit — name/email/phone plus an optional password change.
     * The current password is verified by the caller (AuthController::updateProfile)
     * before this runs, since that check needs to throw a field-specific validation
     * error rather than a generic one.
     */
    public function updateProfile(User $user, array $data): User
    {
        $payload = collect($data)->only(['name', 'email', 'phone'])->toArray();

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return $user->fresh(['company', 'branch', 'roles']);
    }

    public function registerCompany(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(6)),
                'currency_code' => $data['currency_code'] ?? 'USD',
                'timezone' => $data['timezone'] ?? 'UTC',
                'is_active' => true,
                // Self-registration is a separate, pre-existing path from the new
                // platform-onboarding flow — it should keep working immediately,
                // not land in 'pending' (that status is for platform-staff-created
                // clients awaiting activation).
                'status' => 'active',
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => $data['branch_name'] ?? 'Main Branch',
                'code' => 'MAIN',
                'is_main' => true,
                'is_active' => true,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'is_active' => true,
            ]);

            $user->assignRole('company_admin');

            $token = $user->createToken('api')->plainTextToken;

            return [
                'token' => $token,
                'company' => $company,
                'branch' => $branch,
                'user' => $user->load(['company', 'branch', 'roles']),
            ];
        });
    }
}
