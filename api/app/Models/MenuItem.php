<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'type',
        'url',
        'linkable_type',
        'linkable_id',
        'target',
        'rel',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve the destination at read time so links follow renamed slugs.
     */
    public function resolvedUrl(): ?string
    {
        if ($this->type === 'custom') {
            return $this->url;
        }

        $linkable = $this->linkable;

        if ($linkable instanceof Post) {
            return $linkable->type === 'page' ? '/'.$linkable->slug : '/posts/'.$linkable->slug;
        }

        if ($linkable instanceof Term) {
            $segment = match ($linkable->taxonomy) {
                'category' => 'categories',
                'tag' => 'tags',
                default => $linkable->taxonomy,
            };

            return "/{$segment}/{$linkable->slug}";
        }

        return null;
    }
}
