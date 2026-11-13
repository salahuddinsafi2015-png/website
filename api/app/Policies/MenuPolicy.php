<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('menu.manage');
    }

    public function view(User $user, Menu $menu): bool
    {
        return $user->can('menu.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('menu.manage');
    }

    public function update(User $user, Menu $menu): bool
    {
        return $user->can('menu.manage');
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $user->can('menu.manage');
    }
}
