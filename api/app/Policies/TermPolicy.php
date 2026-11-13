<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('term.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('term.manage');
    }

    public function update(User $user, Term $term): bool
    {
        return $user->can('term.manage');
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->can('term.manage');
    }
}
