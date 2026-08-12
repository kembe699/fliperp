<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authService->login($credentials['client_code'], $credentials['email'], $credentials['password']);

        return $this->success($this->authPayload($result['user'], $result['token']), 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['company', 'branch', 'roles']);

        return $this->success($this->authPayload($user));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password you entered is incorrect.'],
            ]);
        }

        $user = $this->authService->updateProfile($request->user(), $data);

        return $this->success($this->authPayload($user), 'Profile updated successfully.');
    }

    public function registerCompany(RegisterCompanyRequest $request): JsonResponse
    {
        $result = $this->authService->registerCompany($request->validated());

        return $this->success([
            ...$this->authPayload($result['user'], $result['token']),
            'company' => new CompanyResource($result['company']),
        ], 'Company registered successfully.', 201);
    }

    protected function authPayload($user, ?string $token = null): array
    {
        return [
            'token' => $token,
            'user' => new UserResource($user),
            'company' => $user->company ? new CompanyResource($user->company) : null,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
