<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->paginate($request->integer('per_page', 15));

        return $this->paginated(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated());

        return $this->success(new UserResource($user), 'User created successfully.', 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->validated());

        return $this->success(new UserResource($user), 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return $this->success(null, 'User deleted successfully.');
    }
}
