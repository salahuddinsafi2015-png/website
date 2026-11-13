<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostSummaryResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(
            (int) $request->integer('per_page', (int) config('cms.api.per_page', 15)),
            (int) config('cms.api.max_per_page', 50),
        );

        $posts = Post::query()
            ->published()
            ->ofType((string) $request->string('type', 'post'))
            ->filter([
                'search' => $request->string('search')->toString() ?: null,
                'category' => $request->string('category')->toString() ?: null,
                'tag' => $request->string('tag')->toString() ?: null,
                'author' => $request->integer('author') ?: null,
            ])
            ->with(['author', 'featuredMedia', 'categories', 'tags'])
            ->withCount(['comments' => fn ($query) => $query->approved()])
            ->orderByDesc('is_sticky')
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return PostSummaryResource::collection($posts);
    }

    public function show(Request $request, string $slug): PostResource|Response
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->when($request->filled('type'), fn ($query) => $query->ofType((string) $request->string('type')))
            ->with(['author', 'featuredMedia', 'ogImage', 'categories', 'tags'])
            ->withCount(['comments' => fn ($query) => $query->approved()])
            ->first();

        if (! $post) {
            abort(Response::HTTP_NOT_FOUND, 'Post not found.');
        }

        $this->guardPassword($request, $post);

        return new PostResource($post);
    }

    /**
     * Password-protected posts withhold their content until unlocked.
     */
    protected function guardPassword(Request $request, Post $post): void
    {
        if (! $post->isPasswordProtected()) {
            return;
        }

        $submitted = (string) $request->string('password')->toString();

        abort_unless(
            $submitted !== '' && Hash::check($submitted, (string) $post->password),
            Response::HTTP_FORBIDDEN,
            'This post is password protected.',
        );
    }
}
