<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('post.viewAny');
    }

    public function view(User $user, Post $post): bool
    {
        if ($post->isPublic()) {
            return true;
        }

        return $this->update($user, $post);
    }

    public function create(User $user): bool
    {
        return $user->can('post.create');
    }

    public function update(User $user, Post $post): bool
    {
        if ($user->can('post.update.any')) {
            return true;
        }

        return $user->can('post.update.own') && $post->author_id === $user->getKey();
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->can('post.delete.any')) {
            return true;
        }

        return $user->can('post.delete.own') && $post->author_id === $user->getKey();
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can('post.delete.any');
    }

    /**
     * Publishing and scheduling need an explicit capability.
     */
    public function publish(User $user): bool
    {
        return $user->can('post.publish');
    }
}
