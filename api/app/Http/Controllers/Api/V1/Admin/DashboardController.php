<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostSummaryResource;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Post::class);

        $countByStatus = fn (PostStatus $status): int => Post::query()
            ->where('status', $status->value)
            ->count();

        return response()->json([
            'data' => [
                'stats' => [
                    'posts' => Post::query()->ofType('post')->count(),
                    'pages' => Post::query()->ofType('page')->count(),
                    'published' => $countByStatus(PostStatus::Published),
                    'drafts' => $countByStatus(PostStatus::Draft),
                    'pending' => $countByStatus(PostStatus::Pending),
                    'scheduled' => $countByStatus(PostStatus::Future),
                    'comments_pending' => Comment::query()
                        ->where('status', CommentStatus::Pending->value)->count(),
                    'media' => Media::query()->count(),
                    'users' => User::query()->count(),
                ],
                'recent_posts' => PostSummaryResource::collection(
                    Post::query()
                        ->with(['author', 'featuredMedia'])
                        ->latest('updated_at')
                        ->limit(5)
                        ->get()
                ),
                'recent_comments' => Comment::query()
                    ->with('post:id,title,slug')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Comment $comment) => [
                        'id' => $comment->id,
                        'author_name' => $comment->author_name,
                        'content' => str($comment->content)->limit(120)->toString(),
                        'status' => $comment->status->value,
                        'post' => $comment->post?->only(['id', 'title', 'slug']),
                        'created_at' => $comment->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }
}
