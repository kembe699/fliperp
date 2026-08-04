<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\EmployeePortalLoginRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\UserResource;
use App\Services\EmployeePortal\EmployeePortalAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePortalAuthController extends Controller
{
    public function __construct(protected EmployeePortalAuthService $authService) {}

    public function login(EmployeePortalLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authService->login($credentials['email'], $credentials['password']);

        return $this->success([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'employee' => new EmployeeResource($result['employee']),
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }
}
