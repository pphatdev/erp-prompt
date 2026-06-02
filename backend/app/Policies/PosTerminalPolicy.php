<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosTerminalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool          { return $user->hasPermission('pos.terminal.read'); }
    public function view(User $user, PosTerminal $t): bool { return $user->hasPermission('pos.terminal.read'); }
    public function create(User $user): bool           { return $user->hasPermission('pos.terminal.write'); }
    public function update(User $user, PosTerminal $t): bool { return $user->hasPermission('pos.terminal.write'); }
    public function delete(User $user, PosTerminal $t): bool { return $user->hasPermission('pos.terminal.delete'); }
}
