<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('user.manage');
    }

    public function delete(User $user, User $target): bool
    {
        // An administrator cannot delete their own account.
        return $user->can('user.manage') && $user->getKey() !== $target->getKey();
    }
}
