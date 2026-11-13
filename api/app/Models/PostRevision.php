<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRevision extends Model
{
    /** @use HasFactory<\Database\Factories\PostRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'author_id',
        'title',
        'excerpt',
        'content',
        'blocks',
        'status',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'blocks' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Snapshot the current state of a post into a revision row.
     */
    public static function capture(Post $post, ?int $authorId = null): self
    {
        return static::create([
            'post_id' => $post->getKey(),
            'author_id' => $authorId ?? $post->author_id,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'blocks' => $post->blocks,
            'status' => $post->status instanceof \BackedEnum ? $post->status->value : $post->status,
            'meta' => $post->meta,
        ]);
    }
}
