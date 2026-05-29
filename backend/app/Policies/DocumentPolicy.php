<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Document;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('edocs.explorer.read');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasPermission('edocs.explorer.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('edocs.explorer.write');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->hasPermission('edocs.explorer.write');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasPermission('edocs.explorer.delete');
    }

    public function share(User $user, Document $document): bool
    {
        return $user->hasPermission('edocs.explorer.share');
    }

    public function acknowledge(User $user, Document $document): bool
    {
        // Any reader can mark-as-read; gating on `policies.read` covers the
        // policy-distribution scenario without needing a dedicated permission.
        return $user->hasPermission('edocs.explorer.read')
            || $user->hasPermission('edocs.policies.read');
    }
}
