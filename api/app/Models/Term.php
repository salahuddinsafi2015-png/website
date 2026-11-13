<?php

namespace App\Models;

use App\Enums\TermTaxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Term extends Model
{
    /** @use HasFactory<\Database\Factories\TermFactory> */
    use HasFactory;

    protected $fillable = [
        'taxonomy',
        'parent_id',
        'name',
        'slug',
        'description',
        'menu_order',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'count' => 'integer',
            'menu_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Term $term): void {
            $term->taxonomy = $term->taxonomy ?: TermTaxonomy::Category->value;

            if (blank($term->slug)) {
                $term->slug = static::uniqueSlug(
                    $term->name ?: Str::random(8),
                    $term->taxonomy,
                    $term->getKey(),
                );
            }
        });
    }

    public static function uniqueSlug(string $source, string $taxonomy, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('taxonomy', $taxonomy)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('menu_order')->orderBy('name');
    }

    public function scopeTaxonomy(Builder $query, string $taxonomy): Builder
    {
        return $query->where('taxonomy', $taxonomy);
    }

    public function isHierarchical(): bool
    {
        return $this->taxonomy === TermTaxonomy::Category->value;
    }

    /**
     * Recount the number of published posts attached to this term.
     */
    public function syncCount(): void
    {
        $this->forceFill([
            'count' => $this->posts()->published()->count(),
        ])->saveQuietly();
    }
}
