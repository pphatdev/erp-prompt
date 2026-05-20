<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function buildIndexQuery(): Builder
    {
        $query = User::query()->with('roles');
        $query->orderBy('created_at', 'desc');
        return $query;
    }

    /**
     * Create a new user and assign roles.
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user;
        });
    }

    /**
     * Update an existing user.
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user;
        });
    }
}
