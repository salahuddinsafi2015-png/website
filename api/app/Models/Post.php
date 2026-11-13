<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostType;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_id',
        'parent_id',
        'featured_media_id',
        'og_image_id',
        'type',
        'status',
        'title',
        'slug',
        'excerpt',
        'content',
        'blocks',
        'content_format',
        'comment_status',
        'password',
        'menu_order',
        'is_sticky',
        'seo_title',
        'seo_description',
        'meta',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'meta' => 'array',
            'blocks' => 'array',
            'published_at' => 'datetime',
            'is_sticky' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            $post->type = $post->type ?: PostType::Post->value;

            if (blank($post->slug)) {
                $post->slug = static::uniqueSlug(
                    $post->title ?: Str::random(8),
                    $post->type,
                    $post->getKey(),
                );
            }

            // Publishing stamps a date; scheduling requires an explicit date.
            if ($post->status === PostStatus::Published && $post->published_at === null) {
                $post->published_at = now();
            }

            if ($post->status === PostStatus::Draft && $post->published_at !== null) {
                $post->published_at = null;
            }
        });
    }

    /**
     * Build a slug that is unique for the given post type.
     */
    public static function uniqueSlug(string $source, string $type, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('type', $type)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('menu_order');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class)->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->terms()->where('terms.taxonomy', 'category');
    }

    public function tags(): BelongsToMany
    {
        return $this->terms()->where('terms.taxonomy', 'tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('created_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published->value)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PostStatus::Draft->value,
            PostStatus::Pending->value,
            PostStatus::Future->value,
        ]);
    }

    /**
     * Case-insensitive keyword search across the searchable columns.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }

    /**
     * Restrict to posts carrying a term with the given taxonomy and slug.
     */
    public function scopeInTaxonomy(Builder $query, string $taxonomy, string $termSlug): Builder
    {
        return $query->whereHas('terms', function (Builder $query) use ($taxonomy, $termSlug): void {
            $query->where('terms.taxonomy', $taxonomy)->where('terms.slug', $termSlug);
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->search($search))
            ->when(
                ($filters['category'] ?? null),
                fn (Builder $q, string $slug) => $q->inTaxonomy('category', $slug)
            )
            ->when(
                ($filters['tag'] ?? null),
                fn (Builder $q, string $slug) => $q->inTaxonomy('tag', $slug)
            )
            ->when(
                ($filters['author'] ?? null),
                fn (Builder $q, int $authorId) => $q->where('author_id', $authorId)
            );
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isPublic(): bool
    {
        return $this->isPublished() && $this->status !== PostStatus::Private;
    }

    /**
     * Whether the post is protected by a password.
     */
    public function isPasswordProtected(): bool
    {
        return filled($this->password);
    }

    /**
     * Provide a closure used by policies for ownership checks.
     */
    public function ownedBy(User $user): Closure
    {
        return fn (): bool => $this->author_id === $user->getKey();
    }

    public function readingTime(): int
    {
        $words = str_word_count(strip_tags((string) $this->content));

        return max(1, (int) ceil($words / 200));
    }
}
