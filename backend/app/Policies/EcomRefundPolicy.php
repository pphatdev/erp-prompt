<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\EcomRefund;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcomRefundPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool         { return $user->hasPermission('ecommerce.refunds.read'); }
    public function view(User $user, EcomRefund $r): bool { return $user->hasPermission('ecommerce.refunds.read'); }
    public function create(User $user): bool          { return $user->hasPermission('ecommerce.refunds.write'); }
    public function approve(User $user, EcomRefund $r): bool { return $user->hasPermission('ecommerce.refunds.approve'); }
    public function reject(User $user, EcomRefund $r): bool  { return $user->hasPermission('ecommerce.refunds.approve'); }
}
