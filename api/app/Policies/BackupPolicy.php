<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('backup.manage');
    }

    public function view(User $user, Backup $backup): bool
    {
        return $user->can('backup.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('backup.manage');
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $user->can('backup.manage');
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $user->can('backup.manage');
    }
}
