<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     *
     * Password is intentionally passed as plaintext — the User model's
     * `'password' => 'hashed'` cast hashes it on assignment. Calling
     * Hash::make() here as well risks double-hashing when the cast's
     * `Hash::isHashed()` driver check disagrees with the value (a known
     * Laravel 11 footgun that produces "Invalid credentials" on login).
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user;
        });
    }

    /**
     * Update an existing user. Same hashing contract as createUser — the
     * cast hashes the password on save, do not Hash::make here.
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            return $user;
        });
    }

    /**
     * Reset a user's password. Plaintext is passed — the 'hashed' cast
     * hashes exactly once on assignment. Never call Hash::make() here.
     */
    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
    }
}
