<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight representation used by index/listing endpoints.
 *
 * @mixin \App\Models\Post
 */
class PostSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status?->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'path' => $this->path(),
            'featured_media' => new MediaResource($this->whenLoaded('featuredMedia')),
            'author' => new UserResource($this->whenLoaded('author')),
            'categories' => TermResource::collection($this->whenLoaded('categories')),
            'tags' => TermResource::collection($this->whenLoaded('tags')),
            'is_sticky' => (bool) $this->is_sticky,
            'is_password_protected' => $this->isPasswordProtected(),
            'comment_status' => $this->comment_status,
            'comments_count' => $this->whenCounted('comments'),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function path(): string
    {
        return $this->type === 'page'
            ? '/'.$this->slug
            : '/posts/'.$this->slug;
    }
}
