<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'parent_id' => $this->parent_id,
            'label' => $this->label,
            'type' => $this->type,
            'url' => $this->resolvedUrl(),
            'target' => $this->target,
            'rel' => $this->rel,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'linkable' => $this->whenLoaded('linkable', fn () => $this->linkable === null ? null : [
                'type' => $this->linkable->getMorphClass(),
                'id' => $this->linkable->getKey(),
                'slug' => $this->linkable->slug,
                'label' => $this->linkable->title ?? $this->linkable->name,
            ]),
            'linkable_id' => $this->linkable_id,
        ];
    }
}
