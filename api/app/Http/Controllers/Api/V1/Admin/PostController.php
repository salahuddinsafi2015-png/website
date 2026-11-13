<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostRevisionResource;
use App\Http\Resources\PostSummaryResource;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Post::class);

        /** @var User $user */
        $user = $request->user();

        $query = Post::query()->with(['author', 'featuredMedia', 'categories', 'tags']);

        match ($request->string('trashed')->toString()) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => null,
        };

        // Contributors only see their own work unless they can edit anything.
        if (! $user->can('post.update.any')) {
            $query->where('author_id', $user->getKey());
        }

        $query
            ->when($request->filled('type'), fn ($q) => $q->ofType((string) $request->string('type')))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $statuses = array_values(array_filter(explode(',', (string) $request->string('status'))));
                $statuses = array_intersect($statuses, PostStatus::values());

                if ($statuses !== []) {
                    $query->whereIn('status', $statuses);
                }
            })
            ->when($request->filled('search'), fn ($q) => $q->search((string) $request->string('search')))
            ->when($request->filled('author'), fn ($q) => $q->where('author_id', $request->integer('author')));

        $perPage = min($request->integer('per_page', 20), 100);

        return PostSummaryResource::collection(
            $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString()
        );
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        Gate::authorize('create', Post::class);
        $this->ensureMayPublish($request->validated());

        $post = $this->posts->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Post created.',
            'data' => new PostResource($post),
        ], Response::HTTP_CREATED);
    }

    public function show(Post $post): PostResource
    {
        Gate::authorize('view', $post);

        return new PostResource(
            $post->load(['author', 'featuredMedia', 'ogImage', 'categories', 'tags'])
                ->loadCount(['comments' => fn ($query) => $query->approved()])
        );
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);
        $this->ensureMayPublish($request->validated());

        $updated = $this->posts->update($post, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Post updated.',
            'data' => new PostResource($updated),
        ]);
    }

    /**
     * Move a post to the trash (soft delete).
     */
    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $post->forceFill(['status' => PostStatus::Trash])->save();
        $post->delete();

        return response()->json(['message' => 'Post moved to trash.']);
    }

    public function restore(Post $post): JsonResponse
    {
        Gate::authorize('restore', $post);

        $post->restore();
        $post->forceFill(['status' => PostStatus::Draft])->save();

        return response()->json([
            'message' => 'Post restored as a draft.',
            'data' => new PostResource($post->load(['author', 'featuredMedia'])),
        ]);
    }

    public function forceDestroy(Post $post): JsonResponse
    {
        Gate::authorize('forceDelete', $post);

        $post->forceDelete();

        return response()->json(['message' => 'Post permanently deleted.']);
    }

    public function revisions(Post $post): AnonymousResourceCollection
    {
        Gate::authorize('view', $post);

        return PostRevisionResource::collection(
            $post->revisions()->with('author')->latest('id')->paginate(20)
        );
    }

    public function restoreRevision(Post $post, PostRevision $revision, Request $request): PostResource
    {
        Gate::authorize('update', $post);

        if ($revision->post_id !== $post->getKey()) {
            abort(Response::HTTP_NOT_FOUND, 'Revision not found for this post.');
        }

        /** @var User $user */
        $user = $request->user();

        $this->posts->restore($post, $revision, $user);

        return new PostResource($post->load(['author', 'featuredMedia', 'ogImage', 'categories', 'tags']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function ensureMayPublish(array $data): void
    {
        $status = $data['status'] ?? null;

        if (in_array($status, ['publish', 'future', 'private'], true)) {
            abort_unless(
                Gate::allows('publish', Post::class),
                Response::HTTP_FORBIDDEN,
                'You do not have permission to publish or schedule posts.',
            );
        }
    }
}
