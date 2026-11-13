<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can('media.upload')
            && ($media->user_id === $user->getKey() || $user->can('media.delete.any'));
    }

    public function delete(User $user, Media $media): bool
    {
        if ($user->can('media.delete.any')) {
            return true;
        }

        return $user->can('media.upload') && $media->user_id === $user->getKey();
    }
}
