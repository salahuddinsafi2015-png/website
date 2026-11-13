<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

class WidgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('widget.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('widget.manage');
    }

    public function update(User $user, Widget $widget): bool
    {
        return $user->can('widget.manage');
    }

    public function delete(User $user, Widget $widget): bool
    {
        return $user->can('widget.manage');
    }
}
