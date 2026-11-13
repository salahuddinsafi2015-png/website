<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly BlockRenderer $blocks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): Post
    {
        $termIds = Arr::pull($data, 'term_ids', []);
        Arr::forget($data, 'revision_note');

        $data['author_id'] = $author->getKey();
        $data = $this->prepare($data, null, $data['type'] ?? 'post');

        $post = DB::transaction(function () use ($data, $termIds, $author): Post {
            /** @var Post $post */
            $post = Post::create($data);

            if (is_array($termIds)) {
                $post->terms()->sync($termIds);
            }

            PostRevision::capture($post, $author->getKey());

            return $post;
        });

        $this->syncTermCounts(array_map('intval', (array) $termIds));

        return $post->load(['author', 'featuredMedia', 'ogImage', 'categories', 'tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data, User $editor): Post
    {
        $termIds = Arr::pull($data, 'term_ids');
        Arr::forget($data, 'revision_note');

        $previousTermIds = $post->terms()->pluck('terms.id')->all();

        $data = $this->prepare($data, $post, $data['type'] ?? $post->type);

        DB::transaction(function () use ($post, $data, $termIds, $editor): void {
            // Snapshot the pre-edit state so history is never lost.
            PostRevision::capture($post, $editor->getKey());

            $post->fill($data)->save();

            if (is_array($termIds)) {
                $post->terms()->sync($termIds);
            }
        });

        $affected = array_unique(array_merge(
            $previousTermIds,
            array_map('intval', (array) ($termIds ?? [])),
        ));

        $this->syncTermCounts($affected);

        return $post->refresh()->load(['author', 'featuredMedia', 'ogImage', 'categories', 'tags']);
    }

    /**
     * Roll a post back to a revision snapshot.
     *
     * The current state is captured first so a restore is itself undoable.
     * Only content state moves: status, slug, taxonomies, featured media and
     * timestamps are intentionally excluded, which stops an editor without
     * publish permission from re-publishing a post through history.
     */
    public function restore(Post $post, PostRevision $revision, User $editor): Post
    {
        $restored = $this->prepare([
            'title' => (string) $revision->title,
            'excerpt' => $revision->excerpt,
            'content' => (string) $revision->content,
            'blocks' => $revision->blocks,
            'meta' => $revision->meta,
        ], $post, $post->type);

        DB::transaction(function () use ($post, $restored, $editor): void {
            PostRevision::capture($post, $editor->getKey());

            $post->fill($restored)->save();
        });

        return $post->refresh()->load(['author', 'featuredMedia', 'ogImage', 'categories', 'tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Post $post, string $type): array
    {
        if (array_key_exists('blocks', $data) && is_array($data['blocks']) && $data['blocks'] !== []) {
            // Blocks win: the structured tree is the editor's source of truth
            // and the HTML field becomes a derived, sanitised render cache.
            // The renderer escapes every dynamic value (the `html` block runs
            // the purifier), so the output does not need a second pass.
            [$data['blocks'], $data['content']] = $this->blocks->process(array_values($data['blocks']));
            $data['content_format'] = 'blocks';
        } else {
            if (array_key_exists('blocks', $data)) {
                // An empty blocks payload means the editor fell back to HTML;
                // clear the stored structure so the two never disagree.
                $data['blocks'] = null;
            }

            if (array_key_exists('content', $data)) {
                $data['content'] = $this->sanitizer->clean($data['content']);

                if (array_key_exists('blocks', $data)) {
                    $data['content_format'] = 'html';
                }
            }
        }

        if (array_key_exists('slug', $data) && filled($data['slug'])) {
            $data['slug'] = Post::uniqueSlug((string) $data['slug'], $type, $post?->getKey());
        }

        // Derive an excerpt when the editor leaves it empty.
        $hasExcerpt = array_key_exists('excerpt', $data) && filled($data['excerpt']);
        $content = $data['content'] ?? $post?->content;

        if (! $hasExcerpt && filled($content) && (! $post || array_key_exists('content', $data))) {
            $data['excerpt'] = $this->sanitizer->excerptFrom($content);
        }

        return $data;
    }

    /**
     * @param  array<int, int>  $termIds
     */
    public function syncTermCounts(array $termIds): void
    {
        if ($termIds === []) {
            return;
        }

        Term::query()
            ->whereIn('id', array_unique($termIds))
            ->get()
            ->each(fn (Term $term) => $term->syncCount());
    }
}
