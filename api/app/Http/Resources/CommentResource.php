<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'author_name' => $this->author_name,
            'author_url' => $this->author_url,
            'avatar_url' => $this->gravatarUrl(),
            'content' => $this->content,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'children' => CommentResource::collection($this->whenLoaded('children')),
        ];
    }

    /**
     * Gravatar exists independently of this application because no avatar
     * upload is required for guest commenters.
     */
    protected function gravatarUrl(): ?string
    {
        if (filled($this->user?->avatar_url)) {
            return $this->user->avatar_url;
        }

        if (blank($this->author_email)) {
            return null;
        }

        $hash = md5(strtolower(trim((string) $this->author_email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=96";
    }
}
