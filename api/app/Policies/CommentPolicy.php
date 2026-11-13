<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('comment.moderate');
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->can('comment.moderate');
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->can('comment.moderate');
    }
}
