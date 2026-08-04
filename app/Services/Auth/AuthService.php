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
    public function login(string $email, string $password): array
    {
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

    public function registerCompany(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::slug($data['company_name']).'-'.Str::lower(Str::random(6)),
                'currency_code' => $data['currency_code'] ?? 'USD',
                'timezone' => $data['timezone'] ?? 'UTC',
                'is_active' => true,
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
