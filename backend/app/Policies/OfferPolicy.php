<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\Offer;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Offers are an HR / Recruitment artifact. Permission slug mirrors
 * Recruitment so existing role grants extend naturally:
 *
 *   - read   = hrm.recruitment.read OR hrm.recruitment.offer
 *   - write  = hrm.recruitment.write OR hrm.recruitment.offer
 *   - delete = hrm.recruitment.delete (admin-only)
 *
 * The dedicated `hrm.recruitment.offer` slug lets a tenant create a "Offer
 * specialist" role that can ship offers without touching the rest of the
 * recruitment funnel.
 */
class OfferPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.read')
            || $user->hasPermission('hrm.recruitment.offer');
    }

    public function view(User $user, Offer $offer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hrm.recruitment.write')
            || $user->hasPermission('hrm.recruitment.offer');
    }

    public function update(User $user, Offer $offer): bool
    {
        return $this->create($user);
    }

    /**
     * `send` covers send / accept / decline transitions — same gate as
     * write so a tenant who's granted recruitment.write can drive the
     * full pipeline.
     */
    public function send(User $user, Offer $offer): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $user->hasPermission('hrm.recruitment.delete');
    }
}
