<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'email' => $this->when(
                $request->user()?->can('user.manage') || $request->user()?->is($this->resource),
                $this->email,
            ),
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status?->value,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->values()->all()),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
