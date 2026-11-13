<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Post
 */
class PostResource extends JsonResource
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
            'path' => $this->type === 'page' ? '/'.$this->slug : '/posts/'.$this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'blocks' => $this->blocks,
            'content_format' => $this->content_format ?? 'html',
            'featured_media' => new MediaResource($this->whenLoaded('featuredMedia')),
            'og_image' => new MediaResource($this->whenLoaded('ogImage')),
            'author' => new UserResource($this->whenLoaded('author')),
            'parent_id' => $this->parent_id,
            'categories' => TermResource::collection($this->whenLoaded('categories')),
            'tags' => TermResource::collection($this->whenLoaded('tags')),
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'meta' => $this->meta,
            'is_sticky' => (bool) $this->is_sticky,
            'is_password_protected' => $this->isPasswordProtected(),
            'comment_status' => $this->comment_status,
            'comments_count' => $this->whenCounted('comments'),
            'reading_time' => $this->readingTime(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
