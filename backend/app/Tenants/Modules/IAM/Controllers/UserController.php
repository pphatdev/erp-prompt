<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Tenants\Modules\IAM\Resources\UserResource;
use App\Tenants\Modules\IAM\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    use Paginates;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $paginator = $this->paginateQuery($this->userService->buildIndexQuery(), $request);

        return $this->paginatedResponse(UserResource::class, $paginator, $request);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): UserResource
    {
        Gate::authorize('create', User::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = $this->userService->createUser($data);
        return new UserResource($user->load('roles'));
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);
        return new UserResource($user->load('roles.permissions'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'password' => 'sometimes|string|min:8',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $user = $this->userService->updateUser($user, $data);
        return new UserResource($user->load('roles'));
    }

    /**
     * Reset the specified user's password.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $this->userService->resetPassword($user, $data['password']);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }
}
