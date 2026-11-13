<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function index(string $slug): AnonymousResourceCollection
    {
        $post = $this->findPublishedPost($slug);

        $comments = Comment::query()
            ->where('post_id', $post->getKey())
            ->whereNull('parent_id')
            ->approved()
            ->with(['user', 'children' => fn ($query) => $query->approved()->with('user')])
            ->oldest()
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, string $slug): JsonResponse
    {
        $post = $this->findPublishedPost($slug);

        abort_unless(
            (bool) config('cms.comments.enabled', true),
            Response::HTTP_FORBIDDEN,
            'Comments are disabled on this site.',
        );

        abort_if(
            $post->comment_status !== 'open',
            Response::HTTP_FORBIDDEN,
            'Comments are closed for this post.',
        );

        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            // Threading must stay inside the same post and remain shallow.
            $parent = Comment::query()->findOrFail($parentId);

            abort_if(
                $parent->post_id !== $post->getKey(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The parent comment belongs to a different post.',
            );

            abort_if(
                $this->depthOf($parent) >= (int) config('cms.comments.max_depth', 3),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The maximum comment depth has been reached.',
            );
        }

        $requiresModeration = (bool) config('cms.comments.require_moderation', true);

        $comment = Comment::create([
            'post_id' => $post->getKey(),
            'parent_id' => $parentId,
            'user_id' => $request->user()?->getKey(),
            'author_name' => $data['author_name'],
            'author_email' => $data['author_email'],
            'author_url' => $data['author_url'] ?? null,
            'content' => strip_tags($data['content']),
            'status' => $requiresModeration ? CommentStatus::Pending : CommentStatus::Approved,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'message' => $requiresModeration
                ? 'Thanks! Your comment is awaiting moderation.'
                : 'Your comment has been posted.',
            'data' => new CommentResource($comment->load('user')),
        ], Response::HTTP_CREATED);
    }

    protected function findPublishedPost(string $slug): Post
    {
        return Post::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    protected function depthOf(Comment $comment): int
    {
        $depth = 1;

        while ($comment->parent_id !== null) {
            $comment = $comment->parent()->firstOrFail();
            $depth++;
        }

        return $depth;
    }
}
