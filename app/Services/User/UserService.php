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
