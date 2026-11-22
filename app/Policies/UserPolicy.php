<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.manage') && $actor->canAccessBranch($user->branch_id);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->view($actor, $user);
    }
}
