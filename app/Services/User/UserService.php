<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->with('roles')->latest()->paginate($perPage);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'branch_id' => $data['branch_id'] ?? null,
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }

    /**
     * Emails a new user their client code, email, password and how to sign
     * in. A failed send is logged and reported, never fatal: the user exists.
     */
    public function sendWelcomeEmail(User $user, string $password, ?User $createdBy = null): bool
    {
        $company = $user->company_id ? \App\Models\Company::find($user->company_id) : null;
        if (! $company) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($user->email, $user->name)->send(new \App\Mail\UserWelcomeEmail(
                userName: $user->name,
                companyName: $company->name,
                clientCode: $company->client_code,
                email: $user->email,
                password: $password,
                loginUrl: rtrim(config('app.frontend_url'), '/').'/login',
                invitedBy: $createdBy?->name,
                replyToEmail: $createdBy?->email,
            ));

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('User welcome email failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function update(User $user, array $data): User
    {
        $payload = collect($data)->only(['name', 'email', 'phone', 'is_active', 'branch_id'])->toArray();

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        if (array_key_exists('roles', $data) && $data['roles'] !== null) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
